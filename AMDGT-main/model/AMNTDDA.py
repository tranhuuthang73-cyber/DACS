import dgl.nn.pytorch
import torch
import torch.nn as nn
from model import gt_net_drug, gt_net_disease

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

# Check if DGL supports CUDA if device is 'cuda'
if device.type == 'cuda':
    try:
        import dgl
        temp_g = dgl.graph(([0], [0])).to(device)
    except Exception:
        device = torch.device('cpu')


class GatedFusion(nn.Module):
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


class AMNTDDA(nn.Module):
    def __init__(self, args):
        super(AMNTDDA, self).__init__()
        self.args = args
        self.drug_linear = nn.Linear(300, args.hgt_in_dim)
        self.disease_linear = nn.Linear(64, args.hgt_in_dim)
        self.protein_linear = nn.Linear(320, args.hgt_in_dim)
        self.gt_drug = gt_net_drug.GraphTransformer(device, args.gt_layer, args.drug_number, args.gt_out_dim, args.gt_out_dim,
                                                    args.gt_head, args.dropout)
        self.gt_disease = gt_net_disease.GraphTransformer(device, args.gt_layer, args.disease_number, args.gt_out_dim,
                                                    args.gt_out_dim, args.gt_head, args.dropout)

        self.hgt_dgl = dgl.nn.pytorch.conv.HGTConv(args.hgt_in_dim, int(args.hgt_in_dim/args.hgt_head), args.hgt_head, 3, 3, args.dropout)
        self.hgt_dgl_last = dgl.nn.pytorch.conv.HGTConv(args.hgt_in_dim, args.hgt_head_dim, args.hgt_head, 3, 3, args.dropout)
        self.hgt = nn.ModuleList()
        for l in range(args.hgt_layer-1):
            self.hgt.append(self.hgt_dgl)
        self.hgt.append(self.hgt_dgl_last)

        encoder_layer = nn.TransformerEncoderLayer(d_model=args.gt_out_dim, nhead=args.tr_head)
        self.drug_trans = nn.TransformerEncoder(encoder_layer, num_layers=args.tr_layer)
        self.disease_trans = nn.TransformerEncoder(encoder_layer, num_layers=args.tr_layer)

        # Better Fusion mechanism
        self.drug_gated_fusion = GatedFusion(args.gt_out_dim)
        self.disease_gated_fusion = GatedFusion(args.gt_out_dim)

        self.drug_tr = nn.Transformer(d_model=args.gt_out_dim, nhead=args.tr_head, num_encoder_layers=3, num_decoder_layers=3, batch_first=True)
        self.disease_tr = nn.Transformer(d_model=args.gt_out_dim, nhead=args.tr_head, num_encoder_layers=3, num_decoder_layers=3, batch_first=True)

        self.mlp = nn.Sequential(
            nn.Linear(args.gt_out_dim * 2, 1024),
            nn.ReLU(),
            nn.Dropout(0.4),
            nn.Linear(1024, 1024),
            nn.ReLU(),
            nn.Dropout(0.4),
            nn.Linear(1024, 256),
            nn.ReLU(),
            nn.Dropout(0.4),
            nn.Linear(256, 2)
        )


    def forward(self, drdr_graph, didi_graph, drdipr_graph, drug_feature, disease_feature, protein_feature, sample):
        dr_sim = self.gt_drug(drdr_graph)
        di_sim = self.gt_disease(didi_graph)

        drug_feature = self.drug_linear(drug_feature)
        disease_feature = self.disease_linear(disease_feature)
        protein_feature = self.protein_linear(protein_feature)

        feature_dict = {
            'drug': drug_feature,
            'disease': disease_feature,
            'protein': protein_feature
        }

        drdipr_graph.ndata['h'] = feature_dict
        g = dgl.to_homogeneous(drdipr_graph, ndata='h')
        feature = torch.cat((drug_feature, disease_feature, protein_feature), dim=0)

        for layer in self.hgt:
            hgt_out = layer(g, feature, g.ndata['_TYPE'], g.edata['_TYPE'], presorted=True)
            feature = hgt_out

        dr_hgt = hgt_out[:self.args.drug_number, :]
        di_hgt = hgt_out[self.args.drug_number:self.args.disease_number+self.args.drug_number, :]

        # [IMPROVEMENT] Gated Fusion instead of simple stacking
        dr_fused = self.drug_gated_fusion(dr_sim, dr_hgt)
        di_fused = self.disease_gated_fusion(di_sim, di_hgt)
        
        # We concatenate current fused with the original features to maintain the 2*dim for MLP
        dr = torch.cat([dr_fused, dr_sim + dr_hgt], dim=-1)
        di = torch.cat([di_fused, di_sim + di_hgt], dim=-1)

        drdi_embedding = torch.mul(dr[sample[:, 0]], di[sample[:, 1]])

        output = self.mlp(drdi_embedding)

        return dr, output
