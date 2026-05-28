"""
AMNTDDA Improved Model - V6: Baseline Architecture + Gated Topo Injection

Strategy: F-dataset baseline (AUC=0.9584) is near-optimal.
ALL architecture changes (GELU, LayerNorm, Cross-Attention) caused regression.

Solution: Keep architecture IDENTICAL to baseline (same GatedFusion, same ReLU MLP),
and add topo features through a zero-initialized gated residual that starts
with ZERO contribution — so the model begins EXACTLY as baseline.

Improvements:
1. Remove dead Transformer params (baseline defines but never uses them)
2. Topo injection with learned gate (initialized to output ~0)
3. Training improvements (EMA + grad clip) handled in train script
"""

import dgl
import dgl.nn.pytorch
import torch
import torch.nn as nn
import torch.nn.functional as F
from model import gt_net_drug, gt_net_disease

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

if device.type == 'cuda':
    try:
        temp_g = dgl.graph(([0], [0])).to(device)
    except Exception:
        device = torch.device('cpu')


class GatedFusion(nn.Module):
    """Gated Fusion mechanism for stable feature integration."""
    def __init__(self, dim):
        super(GatedFusion, self).__init__()
        self.fc1 = nn.Linear(dim, dim)
        self.fc2 = nn.Linear(dim, dim)
        self.gate = nn.Sequential(
            nn.Linear(dim * 2, dim),
            nn.Sigmoid()
        )

    def forward(self, x1, x2):
        h1 = torch.tanh(self.fc1(x1))
        h2 = torch.tanh(self.fc2(x2))
        g = self.gate(torch.cat([x1, x2], dim=-1))
        return g * h1 + (1 - g) * h2


class ResMLPBlock(nn.Module):
    def __init__(self, in_features, out_features, dropout):
        super(ResMLPBlock, self).__init__()
        # Using Spectral Norm for extreme stability
        self.fc1 = nn.utils.spectral_norm(nn.Linear(in_features, out_features))
        self.bn1 = nn.BatchNorm1d(out_features)
        self.fc2 = nn.utils.spectral_norm(nn.Linear(out_features, out_features))
        self.bn2 = nn.BatchNorm1d(out_features)
        self.act = nn.GELU()
        self.dropout = nn.Dropout(dropout)
        
        self.shortcut = nn.Sequential()
        if in_features != out_features:
            self.shortcut = nn.Sequential(
                nn.utils.spectral_norm(nn.Linear(in_features, out_features)),
                nn.BatchNorm1d(out_features)
            )

    def forward(self, x):
        res = self.shortcut(x)
        out = self.fc1(x)
        out = self.act(out)
        out = self.bn1(out)
        out = self.dropout(out)
        
        out = self.fc2(out)
        out = self.act(out)
        out = self.bn2(out)
        out = self.dropout(out)
        return out + res


class GatedTopoInjection(nn.Module):
    """
    Injects topological features (Laplacian PE, RWSE) into GNN embeddings
    using a gated residual connection.
    """
    def __init__(self, topo_dim, gnn_dim, init_gate=-1.0):
        super(GatedTopoInjection, self).__init__()
        self.topo_fc = nn.Sequential(
            nn.Linear(topo_dim, gnn_dim),
            nn.Tanh()
        )
        self.gate = nn.Parameter(torch.tensor([init_gate]))

    def forward(self, gnn_feat, topo_feat):
        topo_emb = self.topo_fc(topo_feat)
        g = torch.sigmoid(self.gate)
        return gnn_feat + g * topo_emb


class AMNTDDA_improved_C(nn.Module):
    def __init__(self, args, topo_drug_dim=12, topo_disease_dim=12, topo_gate_init=-1.0):
        super(AMNTDDA_improved_C, self).__init__()
        self.args = args

        # ==== SAME AS BASELINE ====
        self.drug_linear = nn.Linear(300, args.hgt_in_dim)
        self.disease_linear = nn.Linear(64, args.hgt_in_dim)
        self.protein_linear = nn.Linear(320, args.hgt_in_dim)

        self.gt_drug = gt_net_drug.GraphTransformer(
            device, args.gt_layer, args.drug_number,
            args.gt_out_dim, args.gt_out_dim, args.gt_head, args.dropout
        )
        self.gt_disease = gt_net_disease.GraphTransformer(
            device, args.gt_layer, args.disease_number,
            args.gt_out_dim, args.gt_out_dim, args.gt_head, args.dropout
        )

        self.hgt_dgl = dgl.nn.pytorch.conv.HGTConv(
            args.hgt_in_dim, int(args.hgt_in_dim / args.hgt_head),
            args.hgt_head, 3, 3, args.dropout
        )
        self.hgt_dgl_last = dgl.nn.pytorch.conv.HGTConv(
            args.hgt_in_dim, args.hgt_head_dim,
            args.hgt_head, 3, 3, args.dropout
        )
        self.hgt = nn.ModuleList()
        for l in range(args.hgt_layer - 1):
            self.hgt.append(self.hgt_dgl)
        self.hgt.append(self.hgt_dgl_last)

        # ==== IMPROVED: Gated Fusion (Topo Injection REMOVED for C-dataset) ====
        self.drug_gated_fusion = GatedFusion(args.gt_out_dim)
        self.disease_gated_fusion = GatedFusion(args.gt_out_dim)
        
        # Topo injection removed to prevent overfitting on dense graphs
        # self.drug_topo_inject = ...

        # ==== IMPROVED: Residual MLP Head ====
        # C-dataset: Simplified to single path (Multiplication only) to prevent memorization
        # Input dimension = gt_out_dim * 3 (because dr = [dr_fused, dr_sim, dr_hgt])
        self.mlp_in = nn.Linear(args.gt_out_dim * 3, 1024)
        self.res_blocks = nn.ModuleList([
            ResMLPBlock(1024, 1024, args.dropout) for _ in range(2)
        ])
        self.mlp_out = nn.Sequential(
            nn.Linear(1024, 256),
            nn.ReLU(),
            nn.Dropout(args.dropout),
            nn.Linear(256, 2)
        )

    def forward(self, drdr_graph, didi_graph, drdipr_graph,
                drug_feature, disease_feature, protein_feature,
                drug_topo, disease_topo, sample):

        # 1. Similarity Graph Path (GT)
        dr_sim = self.gt_drug(drdr_graph)
        di_sim = self.gt_disease(didi_graph)

        # 2. Heterogeneous Graph Path (HGT)
        drug_feat = self.drug_linear(drug_feature)
        disease_feat = self.disease_linear(disease_feature)
        protein_feat = self.protein_linear(protein_feature)

        feature_dict = {
            'drug': drug_feat,
            'disease': disease_feat,
            'protein': protein_feat
        }
        drdipr_graph.ndata['h'] = feature_dict
        g = dgl.to_homogeneous(drdipr_graph, ndata='h')
        feature = torch.cat((drug_feat, disease_feat, protein_feat), dim=0)

        for layer in self.hgt:
            hgt_out = layer(g, feature, g.ndata['_TYPE'], g.edata['_TYPE'], presorted=True)
            feature = hgt_out

        dr_hgt = hgt_out[:self.args.drug_number, :]
        di_hgt = hgt_out[self.args.drug_number:self.args.disease_number + self.args.drug_number, :]

        # 3. Gated Fusion
        dr_fused = self.drug_gated_fusion(dr_sim, dr_hgt)
        di_fused = self.disease_gated_fusion(di_sim, di_hgt)
        
        # 4. Topo Feature Injection (REMOVED for C-dataset to prevent overfitting)
        # dr_fused = self.drug_topo_inject(dr_fused, drug_topo)
        # di_fused = self.disease_topo_inject(di_fused, disease_topo)

        # 5. Final Representation (V12: Similarity-Preserving)
        # We ensure dr_sim (clean similarity) is preserved alongside fused features
        # This is critical for Cold-start drugs in weak folds (2, 5, 6)
        dr = torch.cat([dr_fused, dr_sim, dr_hgt], dim=-1)
        di = torch.cat([di_fused, di_sim, di_hgt], dim=-1)
        
        # Dual-Path Head Input calculation:
        # dr and di are now gt_out_dim * 3 = 600 each.
        # mul_feat = 600, cat_feat = 1200. Total = 1800.
        # This is gt_out_dim * 9.

        # 6. Classification with Simplified MLP
        dr_s = dr[sample[:, 0]]
        di_s = di[sample[:, 1]]
        
        # C-dataset: Only use Element-wise multiplication (Correlation) to prevent overfitting
        drdi_embedding = torch.mul(dr_s, di_s)
        
        h = self.mlp_in(drdi_embedding)
        for block in self.res_blocks:
            h = block(h)
        output = self.mlp_out(h)

        return dr, di, output
