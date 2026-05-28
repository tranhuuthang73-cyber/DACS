"""
Topological Feature Utilities for Drug-Disease Association Prediction.

Computes structural features from similarity/adjacency graphs:
1. Laplacian Positional Encoding (LapPE) - captures global graph structure
2. Random Walk Structural Encoding (RWSE) - captures local node structure

These features provide the model with structural information about each node's
position and role within the similarity graph, which raw similarity vectors
cannot capture.
"""

import numpy as np
import warnings


def compute_laplacian_pe(adj_matrix, k=16):
    n = adj_matrix.shape[0]
    k = min(k, n - 2)
    
    if k <= 0:
        return np.zeros((n, 1), dtype=np.float32)
    
    deg = np.array(adj_matrix.sum(axis=1)).flatten()
    deg_inv_sqrt = np.zeros_like(deg)
    nonzero_mask = deg > 1e-10
    deg_inv_sqrt[nonzero_mask] = 1.0 / np.sqrt(deg[nonzero_mask])
    
    D_inv_sqrt = np.diag(deg_inv_sqrt)
    L_norm = np.eye(n) - D_inv_sqrt @ adj_matrix @ D_inv_sqrt
    
    L_norm = (L_norm + L_norm.T) / 2.0
    
    try:
        eigenvalues, eigenvectors = np.linalg.eigh(L_norm)
    except np.linalg.LinAlgError:
        warnings.warn("Eigendecomposition failed, returning zeros")
        return np.zeros((n, k), dtype=np.float32)
    
    idx = np.argsort(eigenvalues)
    eigenvectors = eigenvectors[:, idx]
    
    pe = eigenvectors[:, 1:k + 1].copy()
    
    for i in range(pe.shape[1]):
        max_idx = np.argmax(np.abs(pe[:, i]))
        if pe[max_idx, i] < 0:
            pe[:, i] = -pe[:, i]
    
    return pe.astype(np.float32)


def compute_rwse(adj_matrix, steps=None):
    if steps is None:
        steps = [2, 4, 8, 16]
    
    n = adj_matrix.shape[0]
    
    deg = np.array(adj_matrix.sum(axis=1)).flatten()
    deg = np.maximum(deg, 1e-10)
    P = adj_matrix / deg[:, np.newaxis]
    
    features = []
    Pk = np.eye(n)
    current_step = 0
    
    for step in sorted(steps):
        for _ in range(step - current_step):
            Pk = Pk @ P
        current_step = step
        features.append(np.diag(Pk).copy())
    
    rwse = np.column_stack(features) if features else np.zeros((n, 0))
    return rwse.astype(np.float32)


def compute_topo_features(adj_matrix, pe_dim=16, rw_steps=None):
    if rw_steps is None:
        rw_steps = [2, 4, 8, 16]
    
    lap_pe = compute_laplacian_pe(adj_matrix, k=pe_dim)
    rwse = compute_rwse(adj_matrix, steps=rw_steps)
    
    # NEW: Node Degree (highly informative for sparse nodes in datasets like F)
    deg = np.array(adj_matrix.sum(axis=1)).flatten()
    deg_feat = np.log1p(deg).reshape(-1, 1)
    
    features = np.concatenate([lap_pe, rwse, deg_feat], axis=1)
    
    return features.astype(np.float32)
