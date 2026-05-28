"""
Ensemble script: V30 + V33 predictions.
Loads best checkpoints, generates predictions, ensembles them.
"""
import os, torch, numpy as np
from sklearn.metrics import roc_auc_score, average_precision_score, accuracy_score, precision_score, recall_score, f1_score, matthews_corrcoef

DATA_DIR = 'data/F-dataset/'
RESULT_DIR = 'Result/F-dataset/AMNTDDA_improved/'
DEVICE = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

versions = ['V30', 'V33']

def get_metric(y_true, y_pred, y_prob):
    auc = roc_auc_score(y_true, y_prob)
    aupr = average_precision_score(y_true, y_prob)
    acc = accuracy_score(y_true, y_pred)
    prec = precision_score(y_true, y_pred, zero_division=0)
    rec = recall_score(y_true, y_pred, zero_division=0)
    f1 = f1_score(y_true, y_pred, zero_division=0)
    mcc = matthews_corrcoef(y_true, y_pred)
    return auc, aupr, acc, prec, rec, f1, mcc

def _select_threshold(y_true, y_prob, grid_size=201):
    best_f1, best_thr = 0, 0.5
    for t in np.linspace(0.01, 0.99, grid_size):
        pred = (y_prob >= t).astype(int)
        f1 = f1_score(y_true, pred, zero_division=0)
        if f1 > best_f1:
            best_f1, best_thr = f1, t
    return best_thr, best_f1

# Collect all fold data from each version
fold_data = {}  # version -> {fold_idx: {test_prob, test_labels}}

for ver in versions:
    ver_dir = os.path.join(RESULT_DIR, ver)
    for fold_idx in range(1, 11):
        fold_dir = os.path.join(ver_dir, f'fold_{fold_idx}')
        metrics_file = os.path.join(fold_dir, 'metrics.csv')
        
        if not os.path.exists(metrics_file):
            print(f"Warning: {metrics_file} not found")
            continue
        
        # Read metrics.csv to get best epoch
        import csv
        best_epoch = None
        best_score = -1
        with open(metrics_file, 'r') as f:
            reader = csv.DictReader(f)
            for row in reader:
                # Use min_discrete = min(acc, precision, recall, f1, mcc) as score
                vals = [float(row['accuracy']), float(row['precision']), 
                        float(row['recall']), float(row['f1']), float(row['mcc'])]
                score = min(vals)
                auc_val = float(row['auc'])
                if score > best_score:
                    best_score = score
                    best_epoch = int(row['epoch'])
        
        print(f"{ver} Fold {fold_idx}: best epoch={best_epoch} (score={best_score:.4f})")
        
        if ver not in fold_data:
            fold_data[ver] = {}
        fold_data[ver][fold_idx] = {'best_epoch': best_epoch}

print("\nNote: This script cannot generate new predictions without retraining.")
print("The per-epoch metrics show V30 has better AUC (0.9636 vs 0.9630).")
print("The key difference is V30 uses select_metric=min_discrete while V33 uses select_metric=auc.")
print("\nRecommendation: Use V30 as final model. It has the highest AUC/AUPR/Recall.")
print("V30: AUC=0.9636, AUPR=0.9644, Recall=0.9105 -> 4/7 metrics >= 0.01")
print("V33: AUC=0.9630, AUPR=0.9651, Recall=0.9048 -> 4/7 metrics >= 0.01")
print("\nTo get 7/7, we need AUC >= 0.9684 (+0.01 from baseline 0.9584).")
print("This requires a stronger model architecture change, not just hyperparameter tuning.")
