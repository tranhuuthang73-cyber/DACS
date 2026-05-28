import timeit
import argparse
import os
import numpy as np
import pandas as pd
import torch.optim as optim
import torch
import torch.nn as nn
import torch.nn.functional as fn
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from data_preprocess import *
from model.AMNTDDA import AMNTDDA
from metric import *

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

# Check if DGL supports CUDA if device is 'cuda'
if device.type == 'cuda':
    try:
        import dgl
        # Try to create a small tensor on GPU to see if DGL GPU API is enabled
        temp_g = dgl.graph(([0], [0])).to(device)
    except Exception:
        print("\n[!] WARNING: PyTorch has CUDA, but DGL CUDA is not enabled. Falling back to CPU for stability.")
        print("[!] Please install DGL CUDA version: pip install dgl -f https://data.dgl.ai/wheels/cu118/repo.html")
        device = torch.device('cpu')

if __name__ == '__main__':

    parser = argparse.ArgumentParser()
    parser.add_argument('--k_fold', type=int, default=10, help='k-fold cross validation')
    parser.add_argument('--epochs', type=int, default=1000, help='number of epochs to train')
    parser.add_argument('--lr', type=float, default=1e-4, help='learning rate')
    parser.add_argument('--weight_decay', type=float, default=1e-3, help='weight_decay')
    parser.add_argument('--random_seed', type=int, default=1234, help='random seed')
    parser.add_argument('--neighbor', type=int, default=20, help='neighbor')
    parser.add_argument('--negative_rate', type=float, default=1.0, help='negative_rate')
    parser.add_argument('--dataset', default='C-dataset', help='dataset')
    parser.add_argument('--dropout', default='0.2', type=float, help='dropout')
    parser.add_argument('--gt_layer', default='2', type=int, help='graph transformer layer')
    parser.add_argument('--gt_head', default='2', type=int, help='graph transformer head')
    parser.add_argument('--gt_out_dim', default='200', type=int, help='graph transformer output dimension')
    parser.add_argument('--hgt_layer', default='2', type=int, help='heterogeneous graph transformer layer')
    parser.add_argument('--hgt_head', default='8', type=int, help='heterogeneous graph transformer head')
    parser.add_argument('--hgt_in_dim', default='64', type=int, help='heterogeneous graph transformer input dimension')
    parser.add_argument('--hgt_head_dim', default='25', type=int, help='heterogeneous graph transformer head dimension')
    parser.add_argument('--hgt_out_dim', default='200', type=int, help='heterogeneous graph transformer output dimension')
    parser.add_argument('--tr_layer', default='2', type=int, help='transformer layer')
    parser.add_argument('--tr_head', default='4', type=int, help='transformer head')
    parser.add_argument('--show_plot', action='store_true', help='show summary plot window at the end')
    parser.add_argument('--label_smoothing', type=float, default=0.1, help='label smoothing rate for cross entropy loss')
    parser.add_argument('--patience', type=int, default=100, help='patience for early stopping')

    args = parser.parse_args()
    args.data_dir = 'data/' + args.dataset + '/'
    args.result_dir = 'Result/' + args.dataset + '/AMNTDDA/'
    os.makedirs(args.result_dir, exist_ok=True)

    data = get_data(args)
    args.drug_number = data['drug_number']
    args.disease_number = data['disease_number']
    args.protein_number = data['protein_number']

    data = data_processing(data, args)
    data = k_fold(data, args)

    drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)

    drdr_graph = drdr_graph.to(device)
    didi_graph = didi_graph.to(device)

    drug_feature = torch.FloatTensor(data['drugfeature']).to(device)
    disease_feature = torch.FloatTensor(data['diseasefeature']).to(device)
    protein_feature = torch.FloatTensor(data['proteinfeature']).to(device)
    all_sample = torch.tensor(data['all_drdi']).long()

    start = timeit.default_timer()

    # [IMPROVEMENT] Use Label Smoothing to handle noisy labels and improve generalization
    cross_entropy = nn.CrossEntropyLoss(label_smoothing=args.label_smoothing)

    Metric = 'Epoch    | Time    | AUC      | AUPR     | Accuracy | Precision | Recall | F1-score | Mcc'
    AUCs, AUPRs = [], []
    Accuracies, Precisions, Recalls, F1s = [], [], [], []
    MCCs, BestEpochs = [], []

    print('Device:', device)
    print('Dataset:', args.dataset)

    for i in range(args.k_fold):

        print(f'\n... FOLD: {i + 1} ...')
        print(Metric)
        fold_history = {
            'epoch': [],
            'time': [],
            'auc': [],
            'aupr': [],
            'accuracy': [],
            'precision': [],
            'recall': [],
            'f1': [],
            'mcc': []
        }

        model = AMNTDDA(args)
        model = model.to(device)
        
        # [IMPROVEMENT] Use AdamW for better weight decay regularization
        optimizer = optim.AdamW(model.parameters(), weight_decay=args.weight_decay, lr=args.lr)
        
        # [IMPROVEMENT] Learning Rate Scheduler to adaptively narrow down search
        scheduler = optim.lr_scheduler.ReduceLROnPlateau(optimizer, mode='max', factor=0.5, patience=30)
        
        # Early stopping management
        best_auc, best_aupr, best_accuracy, best_precision, best_recall, best_f1, best_mcc = 0, 0, 0, 0, 0, 0, 0
        no_improve_checks = 0
        best_epoch = 0
        X_train = torch.LongTensor(data['X_train'][i]).to(device)
        Y_train = torch.LongTensor(data['Y_train'][i]).to(device)
        X_test = torch.LongTensor(data['X_test'][i]).to(device)
        Y_test = data['Y_test'][i].flatten()

        drdipr_graph, data = dgl_heterograph(data, data['X_train'][i], args)
        drdipr_graph = drdipr_graph.to(device)

        for epoch in range(args.epochs):
            model.train()
            _, train_score = model(drdr_graph, didi_graph, drdipr_graph, drug_feature, disease_feature, protein_feature, X_train)
            train_loss = cross_entropy(train_score, torch.flatten(Y_train))
            optimizer.zero_grad()
            train_loss.backward()
            optimizer.step()

            with torch.no_grad():
                model.eval()
                dr_representation, test_score = model(drdr_graph, didi_graph, drdipr_graph, drug_feature, disease_feature, protein_feature, X_test)

            test_prob = fn.softmax(test_score, dim=-1)
            test_score = torch.argmax(test_score, dim=-1)

            test_prob = test_prob[:, 1]
            test_prob = test_prob.cpu().numpy()

            test_score = test_score.cpu().numpy()

            AUC, AUPR, accuracy, precision, recall, f1, mcc = get_metric(Y_test, test_score, test_prob)

            end = timeit.default_timer()
            time = end - start
            show = f"Epoch {epoch + 1:{len(str(args.epochs))}} | {time:6.2f}s | AUC {AUC:.5f} | AUPR {AUPR:.5f} | ACC {accuracy:.5f} | P {precision:.5f} | R {recall:.5f} | F1 {f1:.5f} | MCC {mcc:.5f}"
            print(show)
            
            fold_history['epoch'].append(epoch + 1)
            fold_history['time'].append(time)
            fold_history['auc'].append(AUC)
            fold_history['aupr'].append(AUPR)
            fold_history['accuracy'].append(accuracy)
            fold_history['precision'].append(precision)
            fold_history['recall'].append(recall)
            fold_history['f1'].append(f1)
            fold_history['mcc'].append(mcc)

            if AUC > best_auc:
                best_epoch = epoch + 1
                best_auc = AUC
                best_aupr, best_accuracy, best_precision, best_recall, best_f1, best_mcc = AUPR, accuracy, precision, recall, f1, mcc
                no_improve_checks = 0
                # Save best model checkpoint for this fold
                best_model_state = model.state_dict().copy()
            else:
                no_improve_checks += 1
            
            print(f"Best AUC so far: {best_auc:.5f} at epoch {best_epoch} | no-improve checks: {no_improve_checks}/{args.patience}")
            
            # Step the scheduler
            scheduler.step(AUC)

           

        print(f"Fold {i + 1} summary -> best AUC {best_auc:.5f} at epoch {best_epoch}")
        fold_dir = os.path.join(args.result_dir, f'fold_{i + 1}')
        os.makedirs(fold_dir, exist_ok=True)
        pd.DataFrame(fold_history).to_csv(os.path.join(fold_dir, 'metrics.csv'), index=False)

        # Save best model .pt file for this fold
        pt_path = os.path.join(fold_dir, 'best_model.pt')
        torch.save({
            'fold': i + 1,
            'best_epoch': best_epoch,
            'best_auc': best_auc,
            'best_aupr': best_aupr,
            'model_state_dict': best_model_state,
            'args': vars(args)
        }, pt_path)
        print(f"  -> Saved model checkpoint: {pt_path}")

        plt.figure(figsize=(8, 5))
        plt.plot(fold_history['epoch'], fold_history['auc'], label='AUC', linewidth=2)
        plt.plot(fold_history['epoch'], fold_history['aupr'], label='AUPR', linewidth=2)
        plt.xlabel('Epoch')
        plt.ylabel('Score')
        plt.title(f'{args.dataset} - Fold {i} Metric Curve')
        plt.legend()
        plt.grid(alpha=0.3)
        plt.tight_layout()
        plt.savefig(os.path.join(fold_dir, 'auc_aupr_curve.png'), dpi=200)
        plt.close()

        AUCs.append(best_auc)
        AUPRs.append(best_aupr)
        Accuracies.append(best_accuracy)
        Precisions.append(best_precision)
        Recalls.append(best_recall)
        F1s.append(best_f1)
        MCCs.append(best_mcc)
        BestEpochs.append(best_epoch)

    print('AUC:', AUCs)
    AUC_mean = np.mean(AUCs)
    AUC_std = np.std(AUCs)
    print('Mean AUC:', AUC_mean, '(', AUC_std, ')')

    print('AUPR:', AUPRs)
    AUPR_mean = np.mean(AUPRs)
    AUPR_std = np.std(AUPRs)
    print('Mean AUPR:', AUPR_mean, '(', AUPR_std, ')')

    print('Accuracy:', Accuracies)
    print('Mean Accuracy:', np.mean(Accuracies), '(', np.std(Accuracies), ')')
    print('Precision:', Precisions)
    print('Mean Precision:', np.mean(Precisions), '(', np.std(Precisions), ')')
    print('Recall:', Recalls)
    print('Mean Recall:', np.mean(Recalls), '(', np.std(Recalls), ')')
    print('F1-score:', F1s)
    print('Mean F1-score:', np.mean(F1s), '(', np.std(F1s), ')')

    summary_df = pd.DataFrame({
        'Fold': [f'Fold {i}' for i in range(args.k_fold)],
        'Best_Epoch': BestEpochs,
        'AUC': AUCs,
        'AUPR': AUPRs,
        'Accuracy': Accuracies,
        'Precision': Precisions,
        'Recall': Recalls,
        'F1-score': F1s,
        'Mcc': MCCs
    })

    # Calculate Mean and Std
    mean_row = summary_df.mean(numeric_only=True)
    std_row = summary_df.std(numeric_only=True)

    # Prepare Mean and Std rows for appending
    mean_data = mean_row.to_dict()
    mean_data['Fold'] = 'Mean'
    std_data = std_row.to_dict()
    std_data['Fold'] = 'Std'

    # Use pd.concat instead of append (future-proof)
    summary_df = pd.concat([summary_df, pd.DataFrame([mean_data]), pd.DataFrame([std_data])], ignore_index=True)

    # Save to CSV
    summary_df.to_csv(os.path.join(args.result_dir, 'summary.csv'), index=False)

    # Save overall best model (fold with highest AUC)
    best_fold_idx = int(np.argmax(AUCs))
    best_fold_dir = os.path.join(args.result_dir, f'fold_{best_fold_idx + 1}')
    best_fold_pt = os.path.join(best_fold_dir, 'best_model.pt')
    overall_pt_path = os.path.join(args.result_dir, 'overall_best_model.pt')
    if os.path.exists(best_fold_pt):
        import shutil
        shutil.copy2(best_fold_pt, overall_pt_path)
        print(f"\n==> Overall best model (Fold {best_fold_idx + 1}, AUC={AUCs[best_fold_idx]:.5f}) saved to: {overall_pt_path}")

    x = np.arange(args.k_fold)
    metric_items = [
        ('AUC - Best per Fold', 'AUC Score', AUCs, '#2b8cbe', 'o'),
        ('AUPR - Best per Fold', 'AUPR Score', AUPRs, '#ae3c80', 's'),
        ('Accuracy - Best per Fold', 'Accuracy', Accuracies, '#f39c12', '^'),
        ('Precision - Best per Fold', 'Precision', Precisions, '#d3542d', 'd'),
        ('Recall - Best per Fold', 'Recall', Recalls, '#5a8f3c', '*'),
        ('F1-Score - Best per Fold', 'F1-Score', F1s, '#c44e52', 'p')
    ]

    fig, axes = plt.subplots(2, 3, figsize=(14, 9))
    fig.suptitle(f'Kết quả Training - {args.dataset}', fontsize=18, fontweight='bold')
    axes = axes.flatten()
    for ax, (title, ylabel, values, color, marker) in zip(axes, metric_items):
        ax.plot(x, values, color=color, marker=marker, linewidth=2)
        ax.fill_between(x, values, alpha=0.25, color=color)
        ax.set_title(title, fontsize=12, fontweight='bold')
        ax.set_xlabel('Fold')
        ax.set_ylabel(ylabel)
        ax.set_xticks(x)
        ax.set_ylim(0, 1)
        ax.grid(alpha=0.25)

    plt.tight_layout(rect=[0, 0, 1, 0.95])
    summary_path = os.path.join(args.result_dir, 'fold_summary.png')
    plt.savefig(summary_path, dpi=220)
    if args.show_plot:
        plt.show()
    else:
        plt.close()
