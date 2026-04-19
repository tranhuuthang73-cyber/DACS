import matplotlib
matplotlib.use('Agg') # Sử dụng backend không giao diện để tránh treo terminal
import matplotlib.pyplot as plt
import pandas as pd
import numpy as np
import os
import glob
from pathlib import Path

# Create images folder if it doesn't exist
if not os.path.exists('images'):
    os.makedirs('images')
    print('[OK] Created images/ folder')

def plot_fold_results(dataset='C-dataset'):
    """Vẽ biểu đồ kết quả từng fold"""
    result_dir = f'Result/{dataset}/AMNTDDA/'
    
    # Tìm tất cả file CSV từ các fold (chỉ epoch files, không phải summary)
    csv_files = sorted([f for f in glob.glob(os.path.join(result_dir, 'fold_[0-9].csv'))])
    
    if not csv_files:
        print(f"Không tìm thấy file CSV trong {result_dir}")
        print("Đang chạy training... Vui lòng chờ hoặc chạy plot_epoch_results() để xem kết quả per-epoch")
        return
    
    fig, axes = plt.subplots(2, 3, figsize=(15, 10))
    fig.suptitle(f'Kết quả Training - {dataset}', fontsize=16, fontweight='bold')
    
    all_auc = []
    all_aupr = []
    all_acc = []
    all_prec = []
    all_rec = []
    all_f1 = []
    
    for csv_file in csv_files:
        df = pd.read_csv(csv_file)
        fold_num = os.path.basename(csv_file).split('_')[1].split('.')[0]
        
        all_auc.append(df['AUC'].max())
        all_aupr.append(df['AUPR'].max())
        all_acc.append(df['Accuracy'].max())
        all_prec.append(df['Precision'].max())
        all_rec.append(df['Recall'].max())
        all_f1.append(df['F1'].max())
    
    fold_nums = range(len(csv_files))
    
    # AUC
    axes[0, 0].plot(fold_nums, all_auc, marker='o', linewidth=2, markersize=8, color='#2E86AB')
    axes[0, 0].fill_between(fold_nums, all_auc, alpha=0.3, color='#2E86AB')
    axes[0, 0].set_title('AUC - Best per Fold', fontweight='bold')
    axes[0, 0].set_ylabel('AUC Score')
    axes[0, 0].set_xlabel('Fold')
    axes[0, 0].grid(True, alpha=0.3)
    axes[0, 0].set_ylim([0, 1])
    
    # AUPR
    axes[0, 1].plot(fold_nums, all_aupr, marker='s', linewidth=2, markersize=8, color='#A23B72')
    axes[0, 1].fill_between(fold_nums, all_aupr, alpha=0.3, color='#A23B72')
    axes[0, 1].set_title('AUPR - Best per Fold', fontweight='bold')
    axes[0, 1].set_ylabel('AUPR Score')
    axes[0, 1].set_xlabel('Fold')
    axes[0, 1].grid(True, alpha=0.3)
    axes[0, 1].set_ylim([0, 1])
    
    # Accuracy
    axes[0, 2].plot(fold_nums, all_acc, marker='^', linewidth=2, markersize=8, color='#F18F01')
    axes[0, 2].fill_between(fold_nums, all_acc, alpha=0.3, color='#F18F01')
    axes[0, 2].set_title('Accuracy - Best per Fold', fontweight='bold')
    axes[0, 2].set_ylabel('Accuracy')
    axes[0, 2].set_xlabel('Fold')
    axes[0, 2].grid(True, alpha=0.3)
    axes[0, 2].set_ylim([0, 1])
    
    # Precision
    axes[1, 0].plot(fold_nums, all_prec, marker='d', linewidth=2, markersize=8, color='#C73E1D')
    axes[1, 0].fill_between(fold_nums, all_prec, alpha=0.3, color='#C73E1D')
    axes[1, 0].set_title('Precision - Best per Fold', fontweight='bold')
    axes[1, 0].set_ylabel('Precision')
    axes[1, 0].set_xlabel('Fold')
    axes[1, 0].grid(True, alpha=0.3)
    axes[1, 0].set_ylim([0, 1])
    
    # Recall
    axes[1, 1].plot(fold_nums, all_rec, marker='*', linewidth=2, markersize=12, color='#6A994E')
    axes[1, 1].fill_between(fold_nums, all_rec, alpha=0.3, color='#6A994E')
    axes[1, 1].set_title('Recall - Best per Fold', fontweight='bold')
    axes[1, 1].set_ylabel('Recall')
    axes[1, 1].set_xlabel('Fold')
    axes[1, 1].grid(True, alpha=0.3)
    axes[1, 1].set_ylim([0, 1])
    
    # F1-Score
    axes[1, 2].plot(fold_nums, all_f1, marker='p', linewidth=2, markersize=8, color='#BC4749')
    axes[1, 2].fill_between(fold_nums, all_f1, alpha=0.3, color='#BC4749')
    axes[1, 2].set_title('F1-Score - Best per Fold', fontweight='bold')
    axes[1, 2].set_ylabel('F1-Score')
    axes[1, 2].set_xlabel('Fold')
    axes[1, 2].grid(True, alpha=0.3)
    axes[1, 2].set_ylim([0, 1])
    
    plt.tight_layout()
    
    # In thống kê
    print("\n" + "="*60)
    print(f"THỐNG KÊ KẾT QUẢ - {dataset}")
    print("="*60)
    print(f"AUC:       {np.mean(all_auc):.5f} ± {np.std(all_auc):.5f}")
    print(f"AUPR:      {np.mean(all_aupr):.5f} ± {np.std(all_aupr):.5f}")
    print(f"Accuracy:  {np.mean(all_acc):.5f} ± {np.std(all_acc):.5f}")
    print(f"Precision: {np.mean(all_prec):.5f} ± {np.std(all_prec):.5f}")
    print(f"Recall:    {np.mean(all_rec):.5f} ± {np.std(all_rec):.5f}")
    print(f"F1-Score:  {np.mean(all_f1):.5f} ± {np.std(all_f1):.5f}")
    print("="*60 + "\n")
    
    # Save to images folder
    img_path = f'images/{dataset}_fold_comparison.png'
    plt.savefig(img_path, dpi=300, bbox_inches='tight')
    print(f"[OK] Saved: {img_path}")
    # plt.show() - Đã tắt để tránh treo terminal


def plot_epoch_results(dataset='C-dataset', fold=0):
    """Vẽ biểu đồ kết quả per-epoch của một fold"""
    csv_file = f'Result/{dataset}/AMNTDDA/fold_{fold}.csv'
    
    if not os.path.exists(csv_file):
        print(f"Chưa có file: {csv_file}")
        print("Dữ liệu sẽ được tạo ra sau khi training hoàn thành fold này!")
        return
    
    df = pd.read_csv(csv_file)
    
    fig, axes = plt.subplots(2, 3, figsize=(15, 10))
    fig.suptitle(f'Per-Epoch Results - {dataset} - Fold {fold}', fontsize=16, fontweight='bold')
    
    # AUC
    axes[0, 0].plot(df['Epoch'], df['AUC'], linewidth=2, color='#2E86AB')
    axes[0, 0].fill_between(df['Epoch'], df['AUC'], alpha=0.3, color='#2E86AB')
    axes[0, 0].set_title('AUC Progress', fontweight='bold')
    axes[0, 0].set_ylabel('AUC')
    axes[0, 0].grid(True, alpha=0.3)
    
    # AUPR
    axes[0, 1].plot(df['Epoch'], df['AUPR'], linewidth=2, color='#A23B72')
    axes[0, 1].fill_between(df['Epoch'], df['AUPR'], alpha=0.3, color='#A23B72')
    axes[0, 1].set_title('AUPR Progress', fontweight='bold')
    axes[0, 1].set_ylabel('AUPR')
    axes[0, 1].grid(True, alpha=0.3)
    
    # Accuracy
    axes[0, 2].plot(df['Epoch'], df['Accuracy'], linewidth=2, color='#F18F01')
    axes[0, 2].fill_between(df['Epoch'], df['Accuracy'], alpha=0.3, color='#F18F01')
    axes[0, 2].set_title('Accuracy Progress', fontweight='bold')
    axes[0, 2].set_ylabel('Accuracy')
    axes[0, 2].grid(True, alpha=0.3)
    
    # Precision
    axes[1, 0].plot(df['Epoch'], df['Precision'], linewidth=2, color='#C73E1D')
    axes[1, 0].fill_between(df['Epoch'], df['Precision'], alpha=0.3, color='#C73E1D')
    axes[1, 0].set_title('Precision Progress', fontweight='bold')
    axes[1, 0].set_ylabel('Precision')
    axes[1, 0].set_xlabel('Epoch')
    axes[1, 0].grid(True, alpha=0.3)
    
    # Recall
    axes[1, 1].plot(df['Epoch'], df['Recall'], linewidth=2, color='#6A994E')
    axes[1, 1].fill_between(df['Epoch'], df['Recall'], alpha=0.3, color='#6A994E')
    axes[1, 1].set_title('Recall Progress', fontweight='bold')
    axes[1, 1].set_ylabel('Recall')
    axes[1, 1].set_xlabel('Epoch')
    axes[1, 1].grid(True, alpha=0.3)
    
    # F1-Score
    axes[1, 2].plot(df['Epoch'], df['F1'], linewidth=2, color='#BC4749')
    axes[1, 2].fill_between(df['Epoch'], df['F1'], alpha=0.3, color='#BC4749')
    axes[1, 2].set_title('F1-Score Progress', fontweight='bold')
    axes[1, 2].set_ylabel('F1-Score')
    axes[1, 2].set_xlabel('Epoch')
    axes[1, 2].grid(True, alpha=0.3)
    
    plt.tight_layout()
    # Save to images folder
    img_path = f'images/{dataset}_fold_{fold}_progress.png'
    plt.savefig(img_path, dpi=300, bbox_inches='tight')
    print(f"[OK] Saved: {img_path}")
    # plt.show() - Đã tắt để tránh treo terminal


if __name__ == '__main__':
    # Vẽ biểu đồ so sánh các fold
    plot_fold_results('C-dataset')
    
    # Vẽ biểu đồ tiến độ per-epoch của fold 0
    plot_epoch_results('C-dataset', fold=0)
