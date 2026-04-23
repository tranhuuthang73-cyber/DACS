import timeit
import argparse
import os
import random
import numpy as np
import pandas as pd
import torch.optim as optim
import torch
import torch.nn as nn
import torch.nn.functional as fn
from data_preprocess import *
from model.AMNTDDA import AMNTDDA
from metric import *
import dgl
from datetime import datetime
import glob

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

# ==================== SEED MANAGEMENT ====================
def set_seed(seed):
    """
    Đặt seed cho tất cả random generators
    Đảm bảo tái tạo được kết quả
    """
    random.seed(seed)
    np.random.seed(seed)
    torch.manual_seed(seed)
    if torch.cuda.is_available():
        torch.cuda.manual_seed(seed)
        torch.cuda.manual_seed_all(seed)
    dgl.seed(seed)
    print(f"[OK] Seed set to {seed}")
# =========================================================

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
    parser.add_argument('--output_dir', default='Result', help='output directory (default: Result)')
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
    parser.add_argument('--print_every', type=int, default=50, help='print metrics every N epochs (default: 50)')
    parser.add_argument('--use_topo_features', type=bool, default=True, help='use topological features (default: True)')

    args = parser.parse_args()
    args.data_dir = 'data/' + args.dataset + '/'
    args.result_dir = args.output_dir + '/' + args.dataset + '/AMNTDDA/'
    
    # ============ SET SEED FOR REPRODUCIBILITY ============
    set_seed(args.random_seed)
    # ======================================================
    
    # Tạo thư mục output nếu không tồn tại
    os.makedirs(args.result_dir, exist_ok=True)
    print(f'Output directory: {args.result_dir}')

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

    # ============ TOPO FEATURES FOR MODEL ============
    drug_topo_feat = torch.FloatTensor(data['drug_topo_features']).to(device) if 'drug_topo_features' in data else None
    disease_topo_feat = torch.FloatTensor(data['disease_topo_features']).to(device) if 'disease_topo_features' in data else None
    print(f"[OK] Topo features loaded: drug={drug_topo_feat.shape if drug_topo_feat is not None else None}, disease={disease_topo_feat.shape if disease_topo_feat is not None else None}")
    # =================================================

    start = timeit.default_timer()

    # ============ CALCULATE CLASS WEIGHTS ============
    # Xử lý mất cân bằng dữ liệu
    pos_weight = len(data['unsample']) / (len(data['all_drdi']) - len(data['unsample']))
    class_weights = torch.tensor([1.0, pos_weight]).to(device)
    print(f"[OK] Class weights: {class_weights.cpu().numpy()}")
    
    # Sử dụng weighted cross entropy loss
    cross_entropy = nn.CrossEntropyLoss(weight=class_weights)
    # ================================================

    Metric = ('Epoch\t\tTime\t\tAUC\t\tAUPR\t\tAccuracy\t\tPrecision\t\tRecall\t\tF1-score\t\tMcc')
    # Danh sách lưu kết quả tốt nhất của mỗi fold để tổng hợp cuối cùng
    AUCs, AUPRs, Accs, Precs, Recalls, F1s, MCCs, Best_Epochs = [], [], [], [], [], [], [], []

    print('Dataset:', args.dataset)

    for i in range(args.k_fold):

        print('fold:', i)
        print(Metric)

        model = AMNTDDA(args)
        model = model.to(device)
        optimizer = optim.Adam(model.parameters(), weight_decay=args.weight_decay, lr=args.lr)
        
        # ============ LEARNING RATE SCHEDULER ============
        scheduler = optim.lr_scheduler.ReduceLROnPlateau(
            optimizer, 
            mode='max',           # maximize AUC
            factor=0.5,           # reduce lr by 0.5
            patience=20,          # wait 20 epochs before reducing
        )
        # ================================================


        best_auc, best_aupr, best_accuracy, best_precision, best_recall, best_f1, best_mcc = 0, 0, 0, 0, 0, 0, 0
        early_stop_patience = 50
        patience_counter = 0
        
        X_train = torch.LongTensor(data['X_train'][i]).to(device)
        Y_train = torch.LongTensor(data['Y_train'][i]).to(device)
        X_test = torch.LongTensor(data['X_test'][i]).to(device)
        Y_test = data['Y_test'][i].flatten()

        drdipr_graph, data = dgl_heterograph(data, data['X_train'][i], args)
        drdipr_graph = drdipr_graph.to(device)

        # Luu metrics cho moi epoch
        epoch_metrics = {'Epoch': [], 'Time': [], 'AUC': [], 'AUPR': [], 'Accuracy': [], 
                         'Precision': [], 'Recall': [], 'F1': [], 'MCC': []}

        for epoch in range(args.epochs):
            model.train()
            _, train_score = model(drdr_graph, didi_graph, drdipr_graph, drug_feature, disease_feature, protein_feature, X_train, drug_topo_feat, disease_topo_feat)
            train_loss = cross_entropy(train_score, torch.flatten(Y_train))
            optimizer.zero_grad()
            train_loss.backward()
            
            # ============ GRADIENT CLIPPING ============
            torch.nn.utils.clip_grad_norm_(model.parameters(), max_norm=1.0)
            # =========================================
            
            optimizer.step()

            with torch.no_grad():
                model.eval()
                dr_representation, test_score = model(drdr_graph, didi_graph, drdipr_graph, drug_feature, disease_feature, protein_feature, X_test, drug_topo_feat, disease_topo_feat)

            test_prob = fn.softmax(test_score, dim=-1)
            test_score = torch.argmax(test_score, dim=-1)

            test_prob = test_prob[:, 1]
            test_prob = test_prob.cpu().numpy()

            test_score = test_score.cpu().numpy()

            AUC, AUPR, accuracy, precision, recall, f1, mcc = get_metric(Y_test, test_score, test_prob)
            
            # ============ LEARNING RATE SCHEDULING ============
            scheduler.step(AUC)
            # =================================================

            end = timeit.default_timer()
            time = end - start
            show = [epoch + 1, round(time, 2), round(AUC, 5), round(AUPR, 5), round(accuracy, 5),
                       round(precision, 5), round(recall, 5), round(f1, 5), round(mcc, 5)]
            
            # In output mỗi print_every epochs
            if (epoch + 1) % args.print_every == 0 or epoch == 0 or epoch == args.epochs - 1:
                print('\t\t'.join(map(str, show)))
            
            # Luu metrics
            epoch_metrics['Epoch'].append(epoch + 1)
            epoch_metrics['Time'].append(round(time, 2))
            epoch_metrics['AUC'].append(round(AUC, 5))
            epoch_metrics['AUPR'].append(round(AUPR, 5))
            epoch_metrics['Accuracy'].append(round(accuracy, 5))
            epoch_metrics['Precision'].append(round(precision, 5))
            epoch_metrics['Recall'].append(round(recall, 5))
            epoch_metrics['F1'].append(round(f1, 5))
            epoch_metrics['MCC'].append(round(mcc, 5))
            
            if AUC > best_auc:
                best_epoch = epoch + 1
                best_auc = AUC
                best_aupr, best_accuracy, best_precision, best_recall, best_f1, best_mcc = AUPR, accuracy, precision, recall, f1, mcc
                patience_counter = 0  # reset counter
                
                # ============ SAVE BEST MODEL ============
                best_model_path = os.path.join(args.result_dir, f'fold_{i}_best_model.pt')
                try:
                    # Sử dụng file tạm để tránh lỗi 1224 (File locked/Mapped) trên Windows
                    torch.save(model.state_dict(), best_model_path + '.tmp')
                    if os.path.exists(best_model_path):
                        try:
                            os.remove(best_model_path)
                        except:
                            pass # Chấp nhận nếu không xóa được, sẽ rename đè lên
                    os.replace(best_model_path + '.tmp', best_model_path)
                except Exception as e:
                    # Nếu vẫn kẹt, lưu với timestamp để không bao giờ thất bại
                    fallback_path = os.path.join(args.result_dir, f'fold_{i}_best_model_epoch{best_epoch}.pt')
                    torch.save(model.state_dict(), fallback_path)
                    print(f'[WARN] Primary model file locked. Saved to: {fallback_path}')
                # ========================================
                
                print('AUC improved at epoch ', best_epoch, ';\tbest_auc:', best_auc)
            else:
                patience_counter += 1
                # ============ EARLY STOPPING ============
                if patience_counter >= early_stop_patience:
                    print(f'\n[WARN] Early stopping at epoch {epoch + 1} (patience={early_stop_patience})')
                    print(f'  Best AUC: {best_auc} at epoch {best_epoch}')
                    break
                # ====================================
        
        # ============ LOAD BEST MODEL ============
        best_model_path = os.path.join(args.result_dir, f'fold_{i}_best_model.pt')
        # Tìm file model tốt nhất (ưu tiên file primary, nếu không thấy thì tìm file fallback gần nhất)
        actual_load_path = None
        if os.path.exists(best_model_path):
            actual_load_path = best_model_path
        else:
            # Tìm các file fallback có dạng fold_i_best_model_epoch*.pt
            import glob
            fallbacks = glob.glob(os.path.join(args.result_dir, f'fold_{i}_best_model_epoch*.pt'))
            if fallbacks:
                actual_load_path = max(fallbacks, key=os.path.getmtime) # Lấy file mới nhất
        
        if actual_load_path:
            model.load_state_dict(torch.load(actual_load_path))
            print(f'[OK] Loaded best model from: {actual_load_path}')
        # =======================================

        # Luu metrics vao CSV
        df_metrics = pd.DataFrame(epoch_metrics)
        csv_path = args.result_dir + f'fold_{i}.csv'
        df_metrics.to_csv(csv_path, index=False)
        print(f'[OK] Saved fold {i} metrics to: {csv_path}')

        AUCs.append(best_auc)
        AUPRs.append(best_aupr)
        Accs.append(best_accuracy)
        Precs.append(best_precision)
        Recalls.append(best_recall)
        F1s.append(best_f1)
        MCCs.append(best_mcc)
        Best_Epochs.append(best_epoch)
        
        # ============ SAVE FOLD SUMMARY ============
        fold_summary = {
            'Metric': ['Final AUC', 'Final AUPR', 'Final Accuracy', 'Final Precision', 'Final Recall', 'Final F1', 'Final MCC'],
            'Value': [best_auc, best_aupr, best_accuracy, best_precision, best_recall, best_f1, best_mcc],
            'Best_Epoch': [best_epoch] * 7
        }
        df_fold_summary = pd.DataFrame(fold_summary)
        fold_summary_path = os.path.join(args.result_dir, f'fold_{i}_summary.csv')
        df_fold_summary.to_csv(fold_summary_path, index=False)
        print(f'[OK] Saved fold {i} summary to: {fold_summary_path}\n')
        # =========================================

    print('='*80)
    print('AUC:', AUCs)
    AUC_mean = np.mean(AUCs)
    AUC_std = np.std(AUCs)
    print('Mean AUC:', AUC_mean, '(', AUC_std, ')')

    print('AUPR:', AUPRs)
    AUPR_mean = np.mean(AUPRs)
    AUPR_std = np.std(AUPRs)
    print('Mean AUPR:', AUPR_mean, '(', AUPR_std, ')')
    print('='*80)
    
    # ============ TẠO FILE TỔNG HỢP 10-FOLD CHUẨN (GIỐNG ẢNH YÊU CẦU) ============
    results_data = {
        'Fold': [f'Fold {i}' for i in range(len(AUCs))],
        'Best_Epoch': Best_Epochs,
        'AUC': AUCs,
        'AUPR': AUPRs,
        'Accuracy': Accs,
        'Precision': Precs,
        'Recall': Recalls,
        'F1-score': F1s,
        'Mcc': MCCs
    }
    df_results = pd.DataFrame(results_data)

    # Tính Mean và Std
    mean_row = ['Mean', ''] + [np.mean(AUCs), np.mean(AUPRs), np.mean(Accs), np.mean(Precs), np.mean(Recalls), np.mean(F1s), np.mean(MCCs)]
    std_row = ['Std', ''] + [np.std(AUCs), np.std(AUPRs), np.std(Accs), np.std(Precs), np.std(Recalls), np.std(F1s), np.std(MCCs)]
    
    # Tạo DataFrame cho Mean/Std để nối vào
    df_stats = pd.DataFrame([mean_row, std_row], columns=df_results.columns)
    df_final_results = pd.concat([df_results, df_stats], ignore_index=True)

    # Lưu file với timestamp như trong ảnh
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    final_csv_name = f'10_fold_results_{timestamp}.csv'
    final_csv_path = os.path.join(args.result_dir, final_csv_name)
    
    df_final_results.to_csv(final_csv_path, index=False)
    print(f'[OK] Generated REAL comprehensive results file: {final_csv_path}')
    print('\n' + '='*60)
    print('[OK] TRAINING COMPLETE!')
    print('  Improvements applied:')
    print('  [OK] Seed management (reproducibility)')
    print('  [OK] Weighted loss (class imbalance)')
    print('  [OK] Early stopping (efficient training)')
    print('  [OK] Learning rate scheduling (adaptive)')
    print('  [OK] Gradient clipping (stability)')
    print('  [OK] Model checkpointing (best model saved)')
    print('  [OK] Standardized 10-fold reporting (CSV)')
    print('')
    print('  Chay: python plot_results.py')
    print('  De xem bieu do ket qua!')
    print('='*60)



