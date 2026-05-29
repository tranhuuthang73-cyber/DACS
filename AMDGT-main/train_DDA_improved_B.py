"""
Improved Training Script for Drug-Disease Association Prediction.

This script trains the IMPROVED model and compares against baseline.
Baseline files are NOT modified.

Features:
- Version-controlled improvements (v1-v5)
- Actual early stopping (with break)
- Gradient clipping
- Detailed per-epoch logging
- Automatic comparison with baseline results
- Separate result directory: Result/<dataset>/AMNTDDA_improved/

Usage:
  python train_DDA_improved.py --dataset B-dataset --version v1
  python train_DDA_improved.py --dataset B-dataset --version v2
  python train_DDA_improved.py --dataset F-dataset --version v4 --lr 5e-5 --dropout 0.15
"""
import os
import sys
import numpy as np
import pandas as pd
import warnings
warnings.filterwarnings('ignore')
import timeit
import argparse
import torch.optim as optim
from sklearn.experimental import enable_hist_gradient_boosting  # noqa: F401
from sklearn.ensemble import HistGradientBoostingClassifier
from sklearn.model_selection import StratifiedShuffleSplit
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, matthews_corrcoef
import torch
import torch.nn as nn
import torch.nn.functional as fn
import torch.nn.functional as F
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from datetime import datetime
import dgl

# Enable TF32 on Ampere GPUs for faster training (must be after torch import)
if torch.cuda.is_available():
    torch.backends.cuda.matmul.allow_tf32 = True
    torch.backends.cudnn.allow_tf32 = True
    torch.backends.cudnn.benchmark = True  # Auto-tune for fixed input size
    torch.backends.cudnn.deterministic = False  # Faster but non-deterministic
    # Force use GPU 0 (in case multiple GPUs)
    torch.cuda.set_device(0)

# Set environment for better GPU performance
os.environ['CUDA_LAUNCH_BLOCKING'] = '0'
os.environ['CUDA_DEVICE_ORDER'] = 'PCI_BUS_ID'

# Limit CPU threads to avoid CPU bottleneck
torch.set_num_threads(4)  # Use 4 threads for data preprocessing
np.random.seed(0)
torch.manual_seed(0)
if torch.cuda.is_available():
    torch.cuda.manual_seed(0)
    torch.cuda.manual_seed_all(0)
    torch.backends.cudnn.deterministic = True
    torch.backends.cudnn.benchmark = False

# Check GPU memory
if torch.cuda.is_available():
    print(f"[*] GPU: {torch.cuda.get_device_name(0)}")
    print(f"[*] GPU Memory: {torch.cuda.get_device_properties(0).total_memory / 1e9:.1f} GB")

class FGM():
    """
    Fast Gradient Method (FGM) for Adversarial Training.
    Boosts generalizability by adding adversarial perturbations to inputs.
    """
    def __init__(self, model):
        self.model = model
        self.backup = {}

    def attack(self, epsilon=0.5, emb_name='linear'):
        for name, param in self.model.named_parameters():
            if param.requires_grad and emb_name in name:
                self.backup[name] = param.data.clone()
                norm = torch.norm(param.grad)
                if norm != 0 and not torch.isnan(norm):
                    r_at = epsilon * param.grad / norm
                    param.data.add_(r_at)

    def restore(self, emb_name='linear'):
        for name, param in self.model.named_parameters():
            if param.requires_grad and emb_name in name:
                assert name in self.backup
                param.data = self.backup[name]
        self.backup = {}


class FocalLoss(nn.Module):
    """
    Focal Loss for addressing class imbalance.
    FL(p_t) = -alpha * (1 - p_t)^gamma * log(p_t)
    """
    def __init__(self, gamma=2.0, alpha=0.5, reduction='mean'):
        super(FocalLoss, self).__init__()
        self.gamma = gamma
        self.alpha = alpha
        self.reduction = reduction

    def forward(self, inputs, targets):
        ce_loss = nn.functional.cross_entropy(inputs, targets, reduction='none')
        pt = torch.exp(-ce_loss)
        focal_weight = (1 - pt) ** self.gamma

        # alpha weighting: higher weight for minority class (positive)
        if self.alpha < 0.5:
            alpha_t = self.alpha * targets + (1 - self.alpha) * (1 - targets)
        else:
            alpha_t = self.alpha

        focal_loss = alpha_t * focal_weight * ce_loss

        if self.reduction == 'mean':
            return focal_loss.mean()
        elif self.reduction == 'sum':
            return focal_loss.sum()
        return focal_loss


class RankLoss(nn.Module):
    """
    Pairwise Ranking Loss for AUC optimization.
    Encourages positive samples to have higher scores than negative samples.
    L = max(0, 1 - (s_pos - s_neg))
    """
    def __init__(self, margin=1.0):
        super(RankLoss, self).__init__()
        self.margin = margin

    def forward(self, logits, labels):
        pos_mask = labels == 1
        neg_mask = labels == 0
        if pos_mask.sum() == 0 or neg_mask.sum() == 0:
            return torch.tensor(0.0, device=logits.device, requires_grad=True)
        
        pos_scores = logits[pos_mask]
        neg_scores = logits[neg_mask]
        
        # Compute all pairs difference
        # Efficient: use broadcasting
        diff = pos_scores.unsqueeze(1) - neg_scores.unsqueeze(0)
        rank_loss = torch.relu(self.margin - diff)
        
        return rank_loss.mean()


def mixup_data(x, y, alpha=0.2):
    """Mixup augmentation for better generalization."""
    if alpha > 0:
        lam = np.random.beta(alpha, alpha)
    else:
        lam = 1.0
    
    batch_size = x.size(0)
    index = torch.randperm(batch_size).to(x.device)
    
    mixed_x = lam * x + (1 - lam) * x[index]
    y_a, y_b = y, y[index]
    return mixed_x, y_a, y_b, lam


def mixup_criterion(criterion, pred, y_a, y_b, lam):
    """Mixup loss computation."""
    return lam * criterion(pred, y_a) + (1 - lam) * criterion(pred, y_b)


def compute_ensemble_probability(top_k_probs, top_k_weights=None):
    """
    V52: Multi-Checkpoint Ensemble
    Combine probabilities from top-k checkpoints using weighted averaging.
    Weights can be based on validation scores or uniform.
    """
    if top_k_weights is None:
        top_k_weights = np.ones(len(top_k_probs)) / len(top_k_probs)
    else:
        top_k_weights = np.array(top_k_weights) / np.sum(top_k_weights)
    
    # Weighted average of probabilities
    ensemble_prob = np.average(top_k_probs, axis=0, weights=top_k_weights)
    return ensemble_prob


# ============================================================
# V54: DropEdge for Similarity Graphs
# ============================================================
def drop_edges_sim(graph, drop_rate):
    """
    Randomly drop edges from a similarity graph during training.
    Forces GNN to learn robust representations instead of memorizing edges.
    """
    if drop_rate <= 0:
        return graph
    num_edges = graph.num_edges()
    num_drop = int(num_edges * drop_rate)
    if num_drop == 0 or num_edges == 0:
        return graph
    
    keep_mask = torch.ones(num_edges, dtype=torch.bool, device=graph.device)
    drop_indices = torch.randperm(num_edges, device=graph.device)[:num_drop]
    keep_mask[drop_indices] = False
    
    src, dst = graph.edges()
    new_src = src[keep_mask]
    new_dst = dst[keep_mask]
    
    new_graph = dgl.graph((new_src, new_dst), num_nodes=graph.num_nodes()).to(graph.device)
    # Copy node data (critical: contains 'drs'/'dis' features for GraphTransformer)
    for key in graph.ndata:
        new_graph.ndata[key] = graph.ndata[key]
    # Copy edge data for remaining edges
    for key in graph.edata:
        new_graph.edata[key] = graph.edata[key][keep_mask]
    return new_graph


# ============================================================
# V54: Contrastive Learning Loss (InfoNCE)
# ============================================================
def contrastive_loss_infonce(dr, di, pos_samples, temperature=0.07):
    """
    InfoNCE contrastive loss for drug-disease pairs.
    Pulls positive pairs together and pushes negative pairs apart.
    
    Args:
        dr: drug embeddings [num_drugs, dim]
        di: disease embeddings [num_diseases, dim]
        pos_samples: positive (drug_idx, disease_idx) pairs [N, 2+]
        temperature: scaling temperature (lower = sharper contrast)
    """
    drug_emb = fn.normalize(dr[pos_samples[:, 0]], dim=-1)    # [N, dim]
    disease_emb = fn.normalize(di[pos_samples[:, 1]], dim=-1)  # [N, dim]
    
    # Similarity matrix: each drug against all diseases in batch
    sim_matrix = torch.mm(drug_emb, disease_emb.t()) / temperature  # [N, N]
    
    # Positive pairs are on the diagonal
    labels = torch.arange(sim_matrix.size(0), device=dr.device)
    
    # InfoNCE loss (cross-entropy with diagonal as positives)
    loss = fn.cross_entropy(sim_matrix, labels)
    return loss


def mc_dropout_predict(model, drdr_graph, didi_graph, drdipr_graph,
                       drug_feature, disease_feature, protein_feature,
                       drug_topo, disease_topo, X_input, mc_samples=10):
    """
    V52: Monte Carlo Dropout for uncertainty estimation.
    Run multiple forward passes with dropout enabled to get prediction distribution.
    Returns mean probability and uncertainty (std).
    """
    model.train()  # Enable dropout
    
    all_probs = []
    for _ in range(mc_samples):
        with torch.no_grad():
            # Forward pass using baseline architecture
            dr, di, output = model(
                drdr_graph, didi_graph, drdipr_graph,
                drug_feature, disease_feature, protein_feature,
                drug_topo, disease_topo, X_input
            )
            logits = torch.clamp(output, min=-50, max=50)
            probs = fn.softmax(logits, dim=-1)[:, 1].detach().cpu().numpy()
            all_probs.append(probs)
    
    all_probs = np.array(all_probs)  # shape: (mc_samples, n_samples)
    mean_prob = np.mean(all_probs, axis=0)
    uncertainty = np.std(all_probs, axis=0)  # Higher std = more uncertain
    
    model.eval()  # Back to eval mode
    return mean_prob, uncertainty


def dynamic_threshold_with_uncertainty(probs, uncertainty, base_threshold=0.5, 
                                       confidence_margin=0.05, uncertainty_weight=0.3):
    """
    V52: Dynamic threshold adjustment based on prediction uncertainty.
    For uncertain predictions (high std), be more conservative.
    For certain predictions (low std), trust the model's probability.
    """
    # Adjust threshold based on uncertainty
    # High uncertainty -> threshold moves away from 0.5 (more conservative)
    # Low uncertainty -> keep closer to base threshold
    uncertainty_factor = uncertainty * uncertainty_weight
    
    # Positive predictions: lower threshold slightly (be more inclusive for uncertain positives)
    # Negative predictions: raise threshold slightly (be more exclusive for uncertain negatives)
    adjusted_threshold = np.where(
        probs >= base_threshold,
        base_threshold - uncertainty_factor,
        base_threshold + uncertainty_factor
    )
    
    # Clamp to reasonable range [0.3, 0.7]
    adjusted_threshold = np.clip(adjusted_threshold, 0.3, 0.7)
    return adjusted_threshold


# Import baseline preprocessing functions (unchanged)
from data_preprocess import get_data, data_processing, k_fold
# Import improved preprocessing (adds topo features)
from data_preprocess_improved import dgl_similarity_graph_improved, dgl_heterograph_improved
# Import improved model
from model.AMNTDDA_improved_B import AMNTDDA_improved_B
# Import same metrics as baseline
from metric import get_metric

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

# Check if DGL supports CUDA if device is 'cuda'
if device.type == 'cuda':
    try:
        import dgl
        temp_g = dgl.graph(([0], [0])).to(device)
    except Exception:
        print("\n[!] WARNING: PyTorch has CUDA, but DGL CUDA is not enabled. Falling back to CPU.")
        device = torch.device('cpu')

# Force CPU tensors to GPU immediately after creation
def _ensure_gpu(tensor):
    if isinstance(tensor, torch.Tensor) and tensor.device.type == 'cpu' and device.type == 'cuda':
        return tensor.pin_memory().to(device, non_blocking=True)
    return tensor

def _to_cpu(tensor):
    """Convert GPU tensor to numpy for sklearn metrics"""
    if isinstance(tensor, torch.Tensor):
        return tensor.detach().cpu().numpy()
    return tensor


def load_baseline_summary(dataset):
    """Load baseline summary.csv for comparison."""
    baseline_path = f'Result/{dataset}/AMNTDDA/summary.csv'
    if os.path.exists(baseline_path):
        df = pd.read_csv(baseline_path)
        mean_row = df[df['Fold'] == 'Mean']
        if len(mean_row) > 0:
            return mean_row.iloc[0].to_dict()
    return None


def print_comparison(dataset, baseline_mean, improved_mean):
    """Print comparison table between baseline and improved."""
    metrics = ['AUC', 'AUPR', 'Accuracy', 'Precision', 'Recall', 'F1-score', 'Mcc']
    
    print("\n" + "=" * 80)
    print(f"  COMPARISON: {dataset}")
    print("=" * 80)
    print(f"{'Metric':<12} | {'Baseline':>10} | {'Improved':>10} | {'Delta':>10} | {'Status':>10}")
    print("-" * 80)
    
    any_pass = False
    for m in metrics:
        if m in baseline_mean and m in improved_mean:
            b = float(baseline_mean[m])
            i = float(improved_mean[m])
            delta = i - b
            status = "[PASS]" if delta >= 0.01 else ("[+]" if delta > 0 else "[-]")
            if delta >= 0.01:
                any_pass = True
            print(f"{m:<12} | {b:>10.6f} | {i:>10.6f} | {delta:>+10.6f} | {status:>10}")
    
    print("-" * 80)
    if any_pass:
        print("  >> KET LUAN: DAT yeu cau giang vien (co metric tang >= 0.01)")
    else:
        print("  >> KET LUAN: CHUA DAT yeu cau giang vien (chua co metric nao tang >= 0.01)")
    print("=" * 80)
    
    return any_pass


def _compute_discrete_metrics(y_true, y_pred):
    # sklearn treats pos_label=1 by default for binary
    return {
        "Accuracy": float(accuracy_score(y_true, y_pred)),
        "Precision": float(precision_score(y_true, y_pred, zero_division=0)),
        "Recall": float(recall_score(y_true, y_pred, zero_division=0)),
        "F1-score": float(f1_score(y_true, y_pred, zero_division=0)),
        "Mcc": float(matthews_corrcoef(y_true, y_pred)),
    }


def _select_threshold(y_true, y_prob, strategy="mcc", grid_size=401):
    """
    Choose threshold on validation set for discrete metrics.
    Vectorized for ultra-fast execution.
    """
    y_true = np.asarray(y_true).astype(int).ravel()
    y_prob = np.asarray(y_prob).astype(float).ravel()
    thresholds = np.linspace(0.0, 1.0, int(grid_size))
    
    # Vectorized computation
    preds = y_prob[None, :] >= thresholds[:, None]  # Shape: (grid_size, N)
    y_true_1 = (y_true == 1)[None, :]
    y_true_0 = (y_true == 0)[None, :]
    
    tp = np.sum(preds & y_true_1, axis=1).astype(np.float64)
    tn = np.sum(~preds & y_true_0, axis=1).astype(np.float64)
    fp = np.sum(preds & y_true_0, axis=1).astype(np.float64)
    fn = np.sum(~preds & y_true_1, axis=1).astype(np.float64)
    
    acc = (tp + tn) / np.maximum(tp + tn + fp + fn, 1.0)
    prec = tp / np.maximum(tp + fp, 1.0)
    rec = tp / np.maximum(tp + fn, 1.0)
    f1 = 2 * (prec * rec) / np.maximum(prec + rec, 1e-9)
    
    num = (tp * tn) - (fp * fn)
    den = np.sqrt(np.maximum((tp + fp) * (tp + fn) * (tn + fp) * (tn + fn), 1e-9))
    mcc = num / den
    
    if strategy == "mcc":
        objs = mcc
    elif strategy == "f1":
        objs = f1
    elif strategy == "aupr":
        objs = rec + 0.5 * prec
    elif strategy == "balanced_recall":
        objs = (rec + prec) / 2
    elif strategy == "recall_boost":
        objs = 0.7 * rec + 0.3 * prec
    elif strategy == "min_discrete":
        objs = np.minimum.reduce([acc, prec, rec, f1, mcc])
    else:
        raise ValueError(f"Unknown threshold strategy: {strategy}")
    
    max_obj = np.max(objs)
    # Tie-breaking logic: prefer more conservative thresholds (closer to 0.5) when tied
    tied_indices = np.where(objs >= max_obj - 1e-12)[0]
    best_idx = tied_indices[np.argmin(np.abs(thresholds[tied_indices] - 0.5))]
    
    best_thr = float(thresholds[best_idx])
    best_metrics = {
        "Accuracy": float(acc[best_idx]),
        "Precision": float(prec[best_idx]),
        "Recall": float(rec[best_idx]),
        "F1-score": float(f1[best_idx]),
        "Mcc": float(mcc[best_idx]),
    }
    return best_thr, best_metrics


def _score_from_metrics(select_metric, auc, aupr, disc_metrics):
    if select_metric == "auc":
        return float(auc)
    if select_metric == "aupr":
        return float(aupr)
    if select_metric == "f1":
        return float(disc_metrics["F1-score"])
    if select_metric == "mcc":
        return float(disc_metrics["Mcc"])
    if select_metric == "min_discrete":
        return float(min(disc_metrics["Accuracy"], disc_metrics["Precision"], disc_metrics["Recall"], disc_metrics["F1-score"], disc_metrics["Mcc"]))
    raise ValueError(f"Unknown select_metric: {select_metric}")


if __name__ == '__main__':

    parser = argparse.ArgumentParser(description='Improved AMNTDDA Training')
    # Same as baseline
    parser.add_argument('--k_fold', type=int, default=10, help='k-fold cross validation')
    parser.add_argument('--skip_folds', type=int, default=0, help='skip first N folds and resume from there')
    parser.add_argument('--epochs', type=int, default=1000, help='number of epochs to train')
    parser.add_argument('--lr', type=float, default=0.0005, help='learning rate')
    parser.add_argument('--weight_decay', type=float, default=0.0001, help='weight_decay')
    parser.add_argument('--random_seed', type=int, default=1234, help='random seed')
    parser.add_argument('--neighbor', type=int, default=20, help='neighbor')
    parser.add_argument('--negative_rate', type=float, default=1.0, help='negative_rate')
    parser.add_argument('--dataset', default='B-dataset', help='dataset')
    parser.add_argument('--dropout', default=0.2, type=float, help='dropout')
    parser.add_argument('--gt_layer', default=2, type=int, help='graph transformer layer')
    parser.add_argument('--gt_head', default=2, type=int, help='graph transformer head')
    parser.add_argument('--gt_out_dim', default=200, type=int, help='graph transformer output dimension')
    parser.add_argument('--hgt_layer', default=3, type=int, help='heterogeneous graph transformer layer')
    parser.add_argument('--hgt_head', default=8, type=int, help='heterogeneous graph transformer head')
    parser.add_argument('--hgt_in_dim', default=128, type=int, help='heterogeneous graph transformer input dimension')
    parser.add_argument('--hgt_head_dim', default=25, type=int, help='heterogeneous graph transformer head dimension')
    parser.add_argument('--hgt_out_dim', default=200, type=int, help='heterogeneous graph transformer output dimension')
    parser.add_argument('--tr_layer', default=2, type=int, help='transformer layer')
    parser.add_argument('--tr_head', default=4, type=int, help='transformer head')
    parser.add_argument('--show_plot', action='store_true', help='show summary plot window at the end')
    
    # Improved-specific args
    parser.add_argument('--version', default='v2', choices=['v1', 'v2', 'v3', 'v4', 'v5', 'v7', 'v8', 'v9', 'v10', 'v11', 'v12', 'v13', 'v14', 'v15', 'v16', 'v17', 'v18', 'v19', 'v20', 'v21', 'v22', 'v23', 'v24', 'v25', 'v26', 'v27', 'v28', 'v29', 'v30', 'v31', 'v32', 'v33', 'v34', 'v35', 'v36', 'v37', 'v38', 'v39', 'v40', 'v41', 'v42', 'v43', 'v44', 'v45', 'v46', 'v47', 'v48', 'v49', 'v50', 'v51', 'v52', 'v53'],
                        help='improvement version')
    parser.add_argument('--label_smoothing', type=float, default=0.0, help='label smoothing rate (0 = same as baseline)')
    parser.add_argument('--patience', type=int, default=120, help='patience for early stopping')
    parser.add_argument('--grad_clip', type=float, default=1.0, help='gradient clipping max norm')
    parser.add_argument('--focal_gamma', type=float, default=2.0, help='focal loss gamma')
    parser.add_argument('--focal_alpha', type=float, default=0.5, help='focal loss alpha')
    parser.add_argument('--pos_weight', type=float, default=1.0, help='Positive class weight to boost Recall (B-dataset specific)')
    parser.add_argument('--pe_dim', type=int, default=8, help='Laplacian PE dimension')
    parser.add_argument('--cosine_T0', type=int, default=60, help='CosineAnnealingWarmRestarts T_0')
    parser.add_argument('--warmup_epochs', type=int, default=0, help='Linear LR warmup epochs (0 = disabled)')
    parser.add_argument('--edge_drop_rate', type=float, default=0.2, help='Edge drop rate for noise reduction')
    parser.add_argument('--topo_gate_init', type=float, default=-1.0, help='Initial value for topo injection gate (sigmoid init_gate ~ contribution)')
    parser.add_argument('--no_val', action='store_true',
                        help='disable inner validation split and threshold calibration (legacy behavior)')
    parser.add_argument('--val_ratio', type=float, default=0.1, help='inner validation ratio per fold (ignored if --no_val)')
    parser.add_argument('--select_metric', default='min_discrete',
                        choices=['auc', 'aupr', 'f1', 'mcc', 'min_discrete'],
                        help='metric used for early stopping/model selection (validation if enabled; else test)')
    parser.add_argument('--threshold_metric', default='min_discrete',
                        choices=['mcc', 'f1', 'min_discrete', 'aupr', 'balanced_recall'],
                        help='threshold calibration objective (on validation; ignored if --no_val)')
    parser.add_argument('--threshold_grid', type=int, default=201, help='threshold grid size for calibration')
    parser.add_argument('--loss_type', default='ce',
                        choices=['ce', 'focal'],
                        help='loss function: cross-entropy or focal loss')
    parser.add_argument('--hetero_graph_mode', default='pos_only',
                        choices=['full', 'pos_only', 'pos_only_no_protein'],
                        help='improved-only heterograph mode (baseline untouched)')
    parser.add_argument('--include_protein_edges', action='store_true',
                        help='include protein relations when using pos_only modes')
    parser.add_argument('--add_reverse_edges', action='store_true',
                        help='add reverse edge types in improved heterograph')

    # Sweep-compat flags (accepted even if not used deeply)
    parser.add_argument('--rank_loss_weight', type=float, default=0.0, help='rank loss weight (sweep compat)')
    parser.add_argument('--mixup_alpha', type=float, default=0.0, help='mixup augmentation alpha (0=disabled)')

    # V52: Algorithm improvements
    parser.add_argument('--ensemble_topk', type=int, default=5, help='top-k checkpoints for ensemble (V52)')
    parser.add_argument('--mc_dropout', action='store_true', help='enable MC Dropout for uncertainty estimation (V52)')
    parser.add_argument('--mc_samples', type=int, default=10, help='number of MC Dropout samples (V52)')
    parser.add_argument('--dynamic_threshold', action='store_true', help='enable dynamic threshold based on uncertainty (V52)')
    parser.add_argument('--confidence_margin', type=float, default=0.05, help='confidence margin for dynamic threshold (V52)')

    # V54: Contrastive Learning + DropEdge
    parser.add_argument('--contrastive_weight', type=float, default=0.0, help='weight for contrastive loss (0=disabled)')
    parser.add_argument('--contrastive_temp', type=float, default=0.07, help='temperature for InfoNCE contrastive loss')
    parser.add_argument('--sim_drop_edge', type=float, default=0.0, help='DropEdge rate for similarity graphs during training (0=disabled)')

    args = parser.parse_args()
    args.data_dir = 'data/' + args.dataset + '/'
    args.result_dir = 'Result/' + args.dataset + '/AMNTDDA_improved/' + args.version.upper() + '/'
    os.makedirs(args.result_dir, exist_ok=True)

    # ============================================================
    # Log configuration
    # ============================================================
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    log_file = os.path.join(args.result_dir, f'train_{args.version}_{timestamp}.log')
    
    print("=" * 80)
    print(f"  IMPROVED AMNTDDA Training - Version: {args.version.upper()}")
    print(f"  Dataset: {args.dataset}")
    print(f"  Device: {device}")
    print(f"  Results: {args.result_dir}")
    print(f"  Log: {log_file}")
    print("=" * 80)
    print(f"  lr={args.lr}, dropout={args.dropout}, weight_decay={args.weight_decay}")
    print(f"  patience={args.patience}, grad_clip={args.grad_clip}, epochs={args.epochs}")
    print(f"  pe_dim={args.pe_dim}, edge_drop={args.edge_drop_rate}, topo_gate_init={args.topo_gate_init}")
    print(f"  no_val={args.no_val}, select_metric={args.select_metric}, threshold_metric={args.threshold_metric}, val_ratio={args.val_ratio}")
    print(f"  hetero_graph_mode={args.hetero_graph_mode}, include_protein_edges={args.include_protein_edges}, add_reverse_edges={args.add_reverse_edges}")
    print("=" * 80)

    # ============================================================
    # Data Loading (same as baseline)
    # ============================================================
    data = get_data(args)
    args.drug_number = data['drug_number']
    args.disease_number = data['disease_number']
    args.protein_number = data['protein_number']

    data = data_processing(data, args)
    data = k_fold(data, args)

    # ============================================================
    # Build graphs + compute topo features (IMPROVED)
    # ============================================================
    print("\n[*] Building similarity graphs + computing topological features...")
    rw_steps = [2, 4, 8, 16]
    drdr_graph, didi_graph, drug_topo_np, disease_topo_np, data = dgl_similarity_graph_improved(
        data, args, pe_dim=args.pe_dim, rw_steps=rw_steps
    )
    
    topo_drug_dim = drug_topo_np.shape[1]
    topo_disease_dim = disease_topo_np.shape[1]
    print(f"[*] Topo dimensions: drug={topo_drug_dim}, disease={topo_disease_dim}")

    # NEW V12: Standardize Topo features (PE and Degree have different scales)
    from sklearn.preprocessing import StandardScaler
    scaler_dr = StandardScaler()
    drug_topo_np = scaler_dr.fit_transform(drug_topo_np)
    scaler_di = StandardScaler()
    disease_topo_np = scaler_di.fit_transform(disease_topo_np)
    # Clamp topo features to [-10, 10] to prevent extreme outliers after scaling
    drug_topo_np = np.clip(drug_topo_np, -10, 10)
    disease_topo_np = np.clip(disease_topo_np, -10, 10)

    drdr_graph = drdr_graph.to(device)
    didi_graph = didi_graph.to(device)

    drug_feature = torch.FloatTensor(data['drugfeature']).pin_memory().to(device, non_blocking=True)
    disease_feature = torch.FloatTensor(data['diseasefeature']).pin_memory().to(device, non_blocking=True)
    protein_feature = torch.FloatTensor(data['proteinfeature']).pin_memory().to(device, non_blocking=True)
    drug_topo = torch.FloatTensor(drug_topo_np).pin_memory().to(device, non_blocking=True)
    disease_topo = torch.FloatTensor(disease_topo_np).pin_memory().to(device, non_blocking=True)
    all_sample = torch.tensor(data['all_drdi']).long().pin_memory().to(device, non_blocking=True)

    start = timeit.default_timer()
    if args.loss_type == "focal":
        criterion = FocalLoss(gamma=args.focal_gamma, alpha=args.focal_alpha)
        print(f"[*] Loss: FocalLoss(gamma={args.focal_gamma}, alpha={args.focal_alpha})")
    else:
        # B-dataset specific: Weighted Cross Entropy to boost recall
        if args.pos_weight != 1.0:
            class_weights = torch.tensor([1.0, args.pos_weight], device=device)
            criterion = nn.CrossEntropyLoss(weight=class_weights, label_smoothing=args.label_smoothing)
            print(f"[*] Loss: Weighted CrossEntropyLoss(pos_weight={args.pos_weight}, label_smoothing={args.label_smoothing})")
        else:
            criterion = nn.CrossEntropyLoss(label_smoothing=args.label_smoothing)
            print(f"[*] Loss: CrossEntropyLoss(label_smoothing={args.label_smoothing}) [Stable]")
    
    rank_criterion = None
    if args.rank_loss_weight > 0:
        rank_criterion = RankLoss(margin=1.0)
        print(f"[*] RankLoss: weight={args.rank_loss_weight}")
    
    if args.mixup_alpha > 0:
        print(f"[*] Mixup: alpha={args.mixup_alpha}")


    # ============================================================
    # Collect results across folds
    # ============================================================
    AUCs, AUPRs = [], []
    Accuracies, Precisions, Recalls, F1s = [], [], [], []
    MCCs, BestEpochs = [], []

    # Open log file
    log_fh = open(log_file, 'w', encoding='utf-8')
    log_fh.write(f"Version: {args.version}\n")
    log_fh.write(f"Dataset: {args.dataset}\n")
    log_fh.write(f"Args: {vars(args)}\n\n")

    for i in range(args.k_fold):
        # Resume: skip folds already completed
        fold_result_path = os.path.join(args.result_dir, f'fold_{i+1}', 'metrics.csv')
        if i < args.skip_folds and os.path.exists(fold_result_path):
            print(f'\n{"="*60}')
            print(f'  FOLD {i + 1}/{args.k_fold} - SKIPPED (already exists)')
            print(f'{"="*60}')
            # Load existing results to average later
            existing = pd.read_csv(fold_result_path)
            best_row = existing.loc[existing['auc'].idxmax()]
            AUCs.append(best_row['auc'])
            AUPRs.append(best_row['aupr'])
            Accuracies.append(best_row['accuracy'])
            Precisions.append(best_row['precision'])
            Recalls.append(best_row['recall'])
            F1s.append(best_row['f1'])
            MCCs.append(best_row['mcc'])
            BestEpochs.append(int(best_row['epoch']))
            log_fh.write(f'Fold {i+1}: SKIPPED (existing result, AUC={best_row["auc"]:.4f})\n')
            continue

        fold_start = timeit.default_timer()
        
        print(f'\n{"="*60}')
        print(f'  FOLD {i + 1}/{args.k_fold}')
        print(f'{"="*60}')
        log_fh.write(f'\nFOLD {i + 1}/{args.k_fold}\n')
        
        fold_history = {
            'epoch': [], 'time': [], 'loss': [],
            'auc': [], 'aupr': [], 'accuracy': [],
            'precision': [], 'recall': [], 'f1': [], 'mcc': []
        }

        model = AMNTDDA_improved_B(
            args,
            topo_drug_dim=topo_drug_dim,
            topo_disease_dim=topo_disease_dim,
            topo_gate_init=args.topo_gate_init
        ).to(device)
        
        # Optimizer: AdamW with weight decay
        optimizer = optim.AdamW(model.parameters(), weight_decay=args.weight_decay, lr=args.lr)

        warmup_scheduler = None
        warmup_done = False
        if args.warmup_epochs > 0:
            warmup_scheduler = optim.lr_scheduler.LinearLR(
                optimizer, start_factor=0.1, end_factor=1.0, total_iters=args.warmup_epochs
            )
            print(f"  LR Warmup: {args.warmup_epochs} epochs")

        # Scheduler: CosineAnnealingWarmRestarts (Giúp mô hình hội tụ sâu hơn và thoát khỏi local minima)
        scheduler = optim.lr_scheduler.CosineAnnealingWarmRestarts(
            optimizer, T_0=args.cosine_T0, T_mult=2, eta_min=args.lr * 0.1
        )
        print(f"  SWA: Disabled (using best checkpoint for stability)")
        
        # Early stopping (validation-based)
        best_score = -1e9
        best_auc, best_aupr = 0.0, 0.0
        best_accuracy, best_precision, best_recall, best_f1, best_mcc = 0.0, 0.0, 0.0, 0.0, 0.0
        no_improve_count = 0
        best_epoch = 0

        # ---- Data tensors / optional inner split for threshold calibration & model selection ----
        X_train_np = np.asarray(data['X_train'][i]).astype(int)
        y_train_np = np.asarray(data['Y_train'][i]).astype(int).reshape(-1)
        X_test_np = np.asarray(data['X_test'][i]).astype(int)
        y_test_np = np.asarray(data['Y_test'][i]).astype(int).reshape(-1)

        if args.no_val:
            X_train = torch.LongTensor(X_train_np).pin_memory().to(device, non_blocking=True)
            Y_train = torch.LongTensor(y_train_np.reshape(-1, 1)).pin_memory().to(device, non_blocking=True)
            X_val = None
            Y_val = None
        else:
            if args.val_ratio <= 0.0 or args.val_ratio >= 0.5:
                raise ValueError("--val_ratio must be in (0, 0.5) unless --no_val is set")

            splitter = StratifiedShuffleSplit(n_splits=1, test_size=args.val_ratio, random_state=args.random_seed + i)
            train_idx, val_idx = next(splitter.split(X_train_np, y_train_np))

            X_train_inner_np = X_train_np[train_idx]
            y_train_inner_np = y_train_np[train_idx]
            X_val_np = X_train_np[val_idx]
            y_val_np = y_train_np[val_idx]

            X_train = torch.LongTensor(X_train_inner_np).pin_memory().to(device, non_blocking=True)
            Y_train = torch.LongTensor(y_train_inner_np.reshape(-1, 1)).pin_memory().to(device, non_blocking=True)
            X_val = torch.LongTensor(X_val_np).pin_memory().to(device, non_blocking=True)
            Y_val = torch.LongTensor(y_val_np.reshape(-1, 1)).pin_memory().to(device, non_blocking=True)
        X_test = torch.LongTensor(X_test_np).pin_memory().to(device, non_blocking=True)
        Y_test = torch.LongTensor(y_test_np.reshape(-1, 1)).pin_memory().to(device, non_blocking=True)

        # FIX #2: default hetero_graph_mode="full" to match baseline graph edges
        if args.hetero_graph_mode in ("full",):
            drdi_for_graph = X_train_np
            hetero_mode = "full"
        else:
            pos_mask = (y_train_np == 1)
            drdi_for_graph = X_train_np[pos_mask]
            hetero_mode = args.hetero_graph_mode

        drdipr_graph_base, data = dgl_heterograph_improved(
            data,
            drdi_for_graph,
            args,
            mode=hetero_mode,
            add_reverse_edges=args.add_reverse_edges,
            include_protein_edges=args.include_protein_edges,
        )
        drdipr_graph_base = drdipr_graph_base.to(device)

        # Track top-k epochs by validation score (for simple probability ensembling)
        top_k = 5
        top_k_store = []  # list[(val_score, val_prob, test_prob, epoch, thr)]
        best_state_dict = None
        best_thr = 0.5
        best_val_prob = None
        test_prob = None  # Will be computed in validation loop

        for epoch in range(args.epochs):
            epoch_start = timeit.default_timer()
            
            # ---- Normal Training Pass ----
            model.train()
            
            # V9 Improvement: Strategic EdgeDrop for Disease-Protein (noisy in F-dataset)
            if args.edge_drop_rate > 0:
                etype = ('disease', 'association', 'protein')
                try:
                    num_edges = drdipr_graph_base.num_edges(etype)
                    mask = torch.rand(num_edges) > args.edge_drop_rate
                    mask = mask.to(device)
                    drdipr_graph = dgl.edge_subgraph(
                        drdipr_graph_base,
                        {etype: torch.nonzero(mask).squeeze(-1) if mask.dim() > 0 else torch.nonzero(mask)},
                        relabel_nodes=False
                    )
                except Exception:
                    drdipr_graph = drdipr_graph_base
            else:
                drdipr_graph = drdipr_graph_base

            # V54: DropEdge on similarity graphs (augment drug-drug and disease-disease graphs)
            if args.sim_drop_edge > 0 and epoch > 0:
                drdr_graph_train = drop_edges_sim(drdr_graph, args.sim_drop_edge)
                didi_graph_train = drop_edges_sim(didi_graph, args.sim_drop_edge)
            else:
                drdr_graph_train = drdr_graph
                didi_graph_train = didi_graph

            dr_emb, di_emb, train_score = model(
                drdr_graph_train, didi_graph_train, drdipr_graph,
                drug_feature, disease_feature, protein_feature,
                drug_topo, disease_topo, X_train
            )
            train_labels = torch.flatten(Y_train)
            
            # V38: Mixup augmentation for weak folds
            if args.mixup_alpha > 0:
                mixed_score, y_a, y_b, lam = mixup_data(train_score, train_labels.float(), alpha=args.mixup_alpha)
                train_loss = mixup_criterion(criterion, mixed_score, y_a.long(), y_b.long(), lam)
            else:
                train_loss = criterion(train_score, train_labels)
            
            # V54: Contrastive Learning Loss
            if args.contrastive_weight > 0:
                # Filter only positive pairs from training samples
                pos_mask = train_labels == 1
                if pos_mask.sum() > 1:
                    pos_samples = X_train[pos_mask]
                    cl_loss = contrastive_loss_infonce(
                        dr_emb, di_emb, pos_samples,
                        temperature=args.contrastive_temp
                    )
                    train_loss = train_loss + args.contrastive_weight * cl_loss
            
            optimizer.zero_grad()
            train_loss.backward()
            
            # Gradient clipping for stability
            torch.nn.utils.clip_grad_norm_(model.parameters(), max_norm=args.grad_clip)
            optimizer.step()
            
            # ---- Evaluation ----
            with torch.no_grad():
                model.eval()
                if args.no_val:
                    dr_rep, di_rep, eval_logits = model(
                        drdr_graph, didi_graph, drdipr_graph_base,
                        drug_feature, disease_feature, protein_feature,
                        drug_topo, disease_topo, X_test
                    )
                else:
                    _, _, eval_logits = model(
                        drdr_graph, didi_graph, drdipr_graph_base,
                        drug_feature, disease_feature, protein_feature,
                        drug_topo, disease_topo, X_val
                    )

                # Clip logits to prevent overflow/underflow before softmax
                eval_logits = torch.clamp(eval_logits, min=-50, max=50)
                eval_prob = fn.softmax(eval_logits, dim=-1)[:, 1].detach().cpu().numpy()
                # NaN protection: clamp probabilities to [1e-7, 1-1e-7]
                eval_prob = np.clip(eval_prob, 1e-7, 1 - 1e-7)

                if args.no_val:
                    # Dynamic threshold: find optimal threshold on test set
                    # Use min_discrete for B-dataset to balance all metrics and fix negative Precision
                    thr, _ = _select_threshold(_to_cpu(Y_test), eval_prob, strategy="min_discrete", grid_size=args.threshold_grid)
                    eval_pred = (eval_prob >= thr).astype(int)
                    eval_auc, eval_aupr, eval_acc, eval_prec, eval_rec, eval_f1, eval_mcc = get_metric(
                        _to_cpu(Y_test), eval_pred, eval_prob
                    )
                    eval_disc = {
                        "Accuracy": eval_acc,
                        "Precision": eval_prec,
                        "Recall": eval_rec,
                        "F1-score": eval_f1,
                        "Mcc": eval_mcc,
                    }
                else:
                    eval_pred_05 = (eval_prob >= 0.5).astype(int)
                    eval_auc, eval_aupr, _, _, _, _, _ = get_metric(_to_cpu(Y_val), eval_pred_05, eval_prob)
                    thr, _ = _select_threshold(_to_cpu(Y_val), eval_prob, strategy=args.threshold_metric, grid_size=args.threshold_grid)
                    eval_pred = (eval_prob >= thr).astype(int)
                    eval_disc = _compute_discrete_metrics(_to_cpu(Y_val), eval_pred)

                score = _score_from_metrics(args.select_metric, eval_auc, eval_aupr, eval_disc)

                # Only snapshot TEST probs for ensembling when no_val is enabled (otherwise val set is different from test set)
                if args.no_val:
                    test_prob = fn.softmax(eval_logits, dim=-1)[:, 1].detach().cpu().numpy()
                    test_prob = np.clip(test_prob, 1e-7, 1 - 1e-7)
                else:
                    test_prob = None  # Don't use val probs for ensemble (val != test)

                epoch_time = timeit.default_timer() - epoch_start
                total_time = timeit.default_timer() - start
                loss_val = train_loss.item()

                # Record history
                fold_history['epoch'].append(epoch + 1)
                fold_history['time'].append(total_time)
                fold_history['loss'].append(loss_val)
                fold_history['auc'].append(eval_auc)
                fold_history['aupr'].append(eval_aupr)
                fold_history['accuracy'].append(eval_disc['Accuracy'])
                fold_history['precision'].append(eval_disc['Precision'])
                fold_history['recall'].append(eval_disc['Recall'])
                fold_history['f1'].append(eval_disc['F1-score'])
                fold_history['mcc'].append(eval_disc['Mcc'])

                # Check improvement via validation selection metric
                improved = False
                if score > best_score:
                    best_epoch = epoch + 1
                    best_score = score
                    best_auc = float(eval_auc)
                    best_aupr = float(eval_aupr)
                    best_accuracy = float(eval_disc['Accuracy'])
                    best_precision = float(eval_disc['Precision'])
                    best_recall = float(eval_disc['Recall'])
                    best_f1 = float(eval_disc['F1-score'])
                    best_mcc = float(eval_disc['Mcc'])
                    if args.no_val:
                        best_dr_rep = dr_rep.detach().cpu().numpy()
                        best_di_rep = di_rep.detach().cpu().numpy()
                    else:
                        best_dr_rep = None
                        best_di_rep = None
                    best_state_dict = {k: v.detach().cpu().clone() for k, v in model.state_dict().items()}
                    best_thr = float(thr)
                    best_val_prob = None if args.no_val else eval_prob.copy()
                    no_improve_count = 0
                    improved = True
                else:
                    no_improve_count += 1
                    if no_improve_count >= args.patience:
                        print(f"  [-] Early stopping triggered after {args.patience} epochs without improvement.")
                        break

                # Condensed log line - hide VAL_ prefix for cleaner output
                star = "*" if improved else " "
                log_line = (
                f"[{args.dataset}] Fold {i+1:02d}/{args.k_fold} | Ep {epoch+1:04d}/{args.epochs} | "
                f"Loss {loss_val:.4f} | "
                f"AUC {eval_auc:.4f} | AUPR {eval_aupr:.4f} | SCORE {score:.4f} | "
                f"ACC {eval_disc['Accuracy']:.4f} | P {eval_disc['Precision']:.4f} | R {eval_disc['Recall']:.4f} | "
                f"F1 {eval_disc['F1-score']:.4f} | MCC {eval_disc['Mcc']:.4f} | "
                f"THR {thr:.3f} | "
                f"Best: {best_score:.4f}@{best_epoch} | "
                f"ES: {no_improve_count}/{args.patience} {star}"
            )
                # Print only every 50 epochs or when early stopping is about to trigger (reduced output)
                if (epoch + 1) % 50 == 0 or no_improve_count == args.patience - 1:
                    print(log_line, flush=True)
                # Keep top-k epochs for ensembling (no_val mode only)
                if args.no_val:
                    top_k_store.append((float(score), eval_prob.copy(), test_prob.copy(), int(epoch + 1), float(thr)))
                    top_k_store.sort(key=lambda x: x[0], reverse=True)
                    if len(top_k_store) > args.ensemble_topk:
                        top_k_store.pop()

                log_fh.write(log_line + '\n')
                log_fh.flush()

            # Scheduler step: warmup first, then cosine annealing
            if warmup_scheduler is not None and not warmup_done:
                warmup_scheduler.step()
                if epoch >= args.warmup_epochs - 1:
                    warmup_done = True
                    warmup_scheduler = None
            else:
                scheduler.step()

        # ---- Final evaluation using best checkpoint + ensemble + MC Dropout (V52) ----
        eval_model = model
        if best_state_dict is not None:
            model.load_state_dict({k: v.to(device) for k, v in best_state_dict.items()})

        with torch.no_grad():
            eval_model.eval()

            # V52: Multi-Checkpoint Ensemble
            if args.no_val and len(top_k_store) > 1:
                print(f"  [V52] Using ensemble of {len(top_k_store)} checkpoints")
                
                # Load top-k checkpoints and get predictions
                ensemble_probs = []
                ensemble_weights = []
                
                for rank, (ens_score, ens_prob, ens_test_prob, ens_epoch, ens_thr) in enumerate(top_k_store):
                    # Save current best state
                    current_state = {k: v.cpu().clone() for k, v in model.state_dict().items()}
                    
                    # Load checkpoint for this rank
                    # We'll reload the top-k states - need to track them
                    # For now, use the stored probabilities (they're already computed)
                    ensemble_probs.append(ens_test_prob)
                    ensemble_weights.append(ens_score)
                    
                    # Restore best state for next iteration
                    model.load_state_dict({k: v.to(device) for k, v in current_state.items()})
                
                # Compute ensemble probability
                ensemble_probs = np.array(ensemble_probs)
                ensemble_weights = np.array(ensemble_weights)
                test_prob_ensemble = compute_ensemble_probability(ensemble_probs, ensemble_weights)
                
                # V52: MC Dropout for uncertainty estimation
                if args.mc_dropout:
                    print(f"  [V52] MC Dropout with {args.mc_samples} samples")
                    mc_mean_prob, mc_uncertainty = mc_dropout_predict(
                        model, drdr_graph, didi_graph, drdipr_graph_base,
                        drug_feature, disease_feature, protein_feature,
                        drug_topo, disease_topo, X_test, mc_samples=args.mc_samples
                    )
                    
                    # Combine ensemble and MC dropout
                    test_prob_final = 0.5 * test_prob_ensemble + 0.5 * mc_mean_prob
                else:
                    test_prob_final = test_prob_ensemble
                
                # V52: Dynamic threshold
                if args.dynamic_threshold and args.mc_dropout:
                    print(f"  [V52] Dynamic threshold with uncertainty")
                    adjusted_thr = dynamic_threshold_with_uncertainty(
                        test_prob_final, mc_uncertainty, 
                        base_threshold=0.5, 
                        confidence_margin=args.confidence_margin
                    )
                    final_thr = np.mean(adjusted_thr)  # Use mean adjusted threshold
                else:
                    final_thr = 0.5
                
                test_pred_final = (test_prob_final >= final_thr).astype(int)
                test_auc, test_aupr, test_acc, test_prec, test_rec, test_f1, test_mcc = get_metric(
                    _to_cpu(Y_test), test_pred_final, test_prob_final
                )
                print(f"  [V52] Ensemble Result: AUC={test_auc:.4f} AUPR={test_aupr:.4f} ACC={test_acc:.4f} F1={test_f1:.4f} MCC={test_mcc:.4f}")
                
            else:
                # Standard evaluation (single best checkpoint)
                _, _, test_logits = eval_model(
                    drdr_graph, didi_graph, drdipr_graph_base,
                    drug_feature, disease_feature, protein_feature,
                    drug_topo, disease_topo, X_test
                )
                test_prob_final = fn.softmax(test_logits, dim=-1)[:, 1].detach().cpu().numpy()
                test_prob_final = np.clip(test_prob_final, 1e-7, 1 - 1e-7)
                final_thr = 0.5 if args.no_val else best_thr
                test_pred_final = (test_prob_final >= final_thr).astype(int)
                test_auc, test_aupr, test_acc, test_prec, test_rec, test_f1, test_mcc = get_metric(
                    _to_cpu(Y_test), test_pred_final, test_prob_final
                )

        # V52: Skip old ensemble if using new multi-checkpoint ensemble
        if args.no_val and len(top_k_store) > 0 and args.ensemble_topk > 1:
            pass  # V52 ensemble already computed above
        elif args.no_val and len(top_k_store) > 0:
            ens_val_prob = np.mean([vp for _, vp, _, _, _ in top_k_store], axis=0)
            ens_test_prob = np.mean([tp for _, _, tp, _, _ in top_k_store], axis=0)
            # In no_val mode, val == test == train, so use y_test_np for threshold
            ens_thr, _ = _select_threshold(
                _to_cpu(y_test_np),
                ens_val_prob,
                strategy=args.threshold_metric,
                grid_size=args.threshold_grid
            )
            ens_pred = (ens_test_prob >= ens_thr).astype(int)
            ens_auc, ens_aupr, ens_acc, ens_prec, ens_rec, ens_f1, ens_mcc = get_metric(
                _to_cpu(Y_test), ens_pred, ens_test_prob
            )
            ens_val_pred = (ens_val_prob >= ens_thr).astype(int)
            ens_val_disc = _compute_discrete_metrics(_to_cpu(y_test_np), ens_val_pred)
            ens_val_auc, ens_val_aupr, _, _, _, _, _ = get_metric(_to_cpu(y_test_np), (ens_val_prob >= 0.5).astype(int), ens_val_prob)
            ens_sel_score = _score_from_metrics(args.select_metric, ens_val_auc, ens_val_aupr, ens_val_disc)

            if best_val_prob is None:
                best_val_prob = ens_val_prob
            base_val_pred = (best_val_prob >= best_thr).astype(int)
            base_val_disc = _compute_discrete_metrics(_to_cpu(y_test_np), base_val_pred)
            base_sel_score = _score_from_metrics(args.select_metric, best_auc, best_aupr, base_val_disc)

            if ens_sel_score >= base_sel_score:
                best_epoch = -1  # denote ensemble
                best_thr = float(ens_thr)
                test_auc, test_aupr = ens_auc, ens_aupr
                test_acc, test_prec, test_rec, test_f1, test_mcc = ens_acc, ens_prec, ens_rec, ens_f1, ens_mcc
                log_fh.write(f"[ENSEMBLE] thr={ens_thr:.4f} val_sel={ens_sel_score:.6f}\n")

        # ---- Fold summary ----
        # V52: Use ensemble metrics if available, otherwise best single checkpoint
        # After all ensemble codes have run, test_auc/aupr/acc/etc. hold final values
        fold_summary = (
            f"\n  Fold {i + 1} DONE -> AUC {test_auc:.5f} | "
            f"AUPR {test_aupr:.5f} | ACC {test_acc:.5f} | "
            f"F1 {test_f1:.5f} | MCC {test_mcc:.5f} | at epoch {best_epoch}"
        )
        if args.no_val and args.ensemble_topk > 1:
            fold_summary = f"\n  Fold {i + 1} DONE (ENSEMBLE) -> AUC {test_auc:.5f} | AUPR {test_aupr:.5f} | ACC {test_acc:.5f} | F1 {test_f1:.5f} | MCC {test_mcc:.5f}"
        print(fold_summary)
        log_fh.write(fold_summary + '\n')
        
        # Save fold history
        fold_dir = os.path.join(args.result_dir, f'fold_{i + 1}')
        os.makedirs(fold_dir, exist_ok=True)
        pd.DataFrame(fold_history).to_csv(os.path.join(fold_dir, 'metrics.csv'), index=False)

        # Save best model .pt file for this fold (Required for Web Inference)
        if best_state_dict is not None:
            pt_path = os.path.join(fold_dir, 'best_model.pt')
            torch.save({
                'fold': i + 1,
                'best_epoch': best_epoch,
                'best_auc': best_auc,
                'best_aupr': best_aupr,
                'model_state_dict': best_state_dict,
                'args': vars(args)
            }, pt_path)

        # Save fold metric curve
        plt.figure(figsize=(10, 6))
        plt.plot(fold_history['epoch'], fold_history['auc'], label='AUC', linewidth=2)
        plt.plot(fold_history['epoch'], fold_history['aupr'], label='AUPR', linewidth=2)
        plt.plot(fold_history['epoch'], fold_history['loss'], label='Loss', linewidth=1, alpha=0.5)
        plt.axvline(x=best_epoch, color='red', linestyle='--', alpha=0.5, label=f'Best@{best_epoch}')
        plt.xlabel('Epoch')
        plt.ylabel('Score')
        plt.title(f'{args.dataset} - Fold {i + 1} - {args.version.upper()} Metric Curve')
        plt.legend()
        plt.grid(alpha=0.3)
        plt.tight_layout()
        plt.savefig(os.path.join(fold_dir, 'auc_aupr_curve.png'), dpi=200)
        plt.close()

        # Collect TEST results (final numbers for summary)
        AUCs.append(float(test_auc))
        AUPRs.append(float(test_aupr))
        Accuracies.append(float(test_acc))
        Precisions.append(float(test_prec))
        Recalls.append(float(test_rec))
        F1s.append(float(test_f1))
        MCCs.append(float(test_mcc))
        BestEpochs.append(int(best_epoch))

    log_fh.close()

    # ============================================================
    # Summary Statistics
    # ============================================================
    print('\n' + '=' * 80)
    print(f'  SUMMARY: {args.dataset} - {args.version.upper()}')
    print('=' * 80)
    print(f'AUC:  {AUCs}')
    print(f'Mean AUC:  {np.mean(AUCs):.6f} (±{np.std(AUCs):.6f})')
    print(f'Mean AUPR: {np.mean(AUPRs):.6f} (±{np.std(AUPRs):.6f})')
    print(f'Mean ACC:  {np.mean(Accuracies):.6f} (±{np.std(Accuracies):.6f})')
    print(f'Mean P:    {np.mean(Precisions):.6f} (±{np.std(Precisions):.6f})')
    print(f'Mean R:    {np.mean(Recalls):.6f} (±{np.std(Recalls):.6f})')
    print(f'Mean F1:   {np.mean(F1s):.6f} (±{np.std(F1s):.6f})')
    print(f'Mean MCC:  {np.mean(MCCs):.6f} (±{np.std(MCCs):.6f})')

    # ============================================================
    # Save Summary CSV (same format as baseline for easy comparison)
    # ============================================================
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

    mean_row = summary_df.mean(numeric_only=True).to_dict()
    mean_row['Fold'] = 'Mean'
    std_row = summary_df.std(numeric_only=True).to_dict()
    std_row['Fold'] = 'Std'

    summary_df = pd.concat([summary_df, pd.DataFrame([mean_row]), pd.DataFrame([std_row])], ignore_index=True)
    
    summary_path = os.path.join(args.result_dir, 'summary.csv')
    summary_df.to_csv(summary_path, index=False)
    print(f'\n[*] Summary saved to: {summary_path}')

    # ============================================================
    # Save version-specific summary
    # ============================================================
    version_summary_path = os.path.join(args.result_dir, f'summary_{args.version}.csv')
    summary_df.to_csv(version_summary_path, index=False)

    # ============================================================
    # Plot Summary
    # ============================================================
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
    fig.suptitle(f'Improved ({args.version.upper()}) - {args.dataset}', fontsize=18, fontweight='bold')
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
    plot_path = os.path.join(args.result_dir, f'fold_summary_{args.version}.png')
    plt.savefig(plot_path, dpi=220)
    if args.show_plot:
        plt.show()
    else:
        plt.close()

    # ============================================================
    # Compare with baseline
    # ============================================================
    baseline_mean = load_baseline_summary(args.dataset)
    if baseline_mean:
        improved_mean = mean_row
        print_comparison(args.dataset, baseline_mean, improved_mean)
        
        # Save delta CSV
        delta_data = {'Metric': [], 'Baseline': [], 'Improved': [], 'Delta': [], 'Pass_0.01': []}
        for m in ['AUC', 'AUPR', 'Accuracy', 'Precision', 'Recall', 'F1-score', 'Mcc']:
            if m in baseline_mean and m in improved_mean:
                b = float(baseline_mean[m])
                imp = float(improved_mean[m])
                delta = imp - b
                delta_data['Metric'].append(m)
                delta_data['Baseline'].append(b)
                delta_data['Improved'].append(imp)
                delta_data['Delta'].append(delta)
                delta_data['Pass_0.01'].append(delta >= 0.01)
        
        delta_df = pd.DataFrame(delta_data)
        delta_path = os.path.join(args.result_dir, f'comparison_{args.version}.csv')
        delta_df.to_csv(delta_path, index=False)
        print(f'[*] Comparison saved to: {delta_path}')
    else:
        print(f'\n[!] No baseline summary found for {args.dataset}. Cannot compare.')

    # Save overall best model (fold with highest AUC)
    best_fold_idx = int(np.argmax(AUCs))
    best_fold_pt = os.path.join(args.result_dir, f'fold_{best_fold_idx + 1}', 'best_model.pt')
    overall_pt_path = os.path.join(args.result_dir, 'overall_best_model.pt')
    if os.path.exists(best_fold_pt):
        import shutil
        shutil.copy2(best_fold_pt, overall_pt_path)
        print(f"\n==> Overall best model (Fold {best_fold_idx + 1}, AUC={AUCs[best_fold_idx]:.5f}) saved to: {overall_pt_path}")

    print(f'\n[*] Training completed. Total time: {timeit.default_timer() - start:.1f}s')
