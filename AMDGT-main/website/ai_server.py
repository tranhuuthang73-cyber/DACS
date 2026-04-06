"""
AMDGT AI Microservice
Flask server chỉ để chạy AI predictions
PHP website sẽ gọi vào đây qua HTTP
"""
import os
import sys
import json
import numpy as np
import torch
import torch.nn.functional as F
from flask import Flask, request, jsonify

# Add project root to path
PROJECT_ROOT = os.path.join(os.path.dirname(__file__), '..')
sys.path.insert(0, PROJECT_ROOT)

import argparse
import random
import dgl

app = Flask(__name__)

# Global state
model = None
data = None
args_config = None
drdr_graph = None
didi_graph = None
drdipr_graph = None
drug_feature = None
disease_feature = None
protein_feature = None
drug_topo_feat = None
disease_topo_feat = None

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')


def load_model():
    global model, data, args_config, drdr_graph, didi_graph, drdipr_graph
    global drug_feature, disease_feature, protein_feature, drug_topo_feat, disease_topo_feat
    
    from data_preprocess import get_data, data_processing, k_fold, dgl_similarity_graph, dgl_heterograph
    from model.AMNTDDA import AMNTDDA
    
    print("[AI] Loading model and data...")
    
    args_config = argparse.Namespace(
        k_fold=2, epochs=50, lr=1e-4, weight_decay=1e-3,
        random_seed=1234, neighbor=20, negative_rate=1.0,
        dataset='C-dataset', dropout=0.2, output_dir='Result',
        gt_layer=2, gt_head=2, gt_out_dim=200,
        hgt_layer=2, hgt_head=8, hgt_in_dim=64,
        hgt_head_dim=25, hgt_out_dim=200,
        tr_layer=2, tr_head=4, use_topo_features=True
    )
    args_config.data_dir = os.path.join(PROJECT_ROOT, 'data', args_config.dataset) + '/'
    args_config.result_dir = os.path.join(PROJECT_ROOT, 'Result', args_config.dataset, 'AMNTDDA') + '/'
    
    # Seed
    random.seed(args_config.random_seed)
    np.random.seed(args_config.random_seed)
    torch.manual_seed(args_config.random_seed)
    dgl.seed(args_config.random_seed)
    
    data = get_data(args_config)
    args_config.drug_number = data['drug_number']
    args_config.disease_number = data['disease_number']
    args_config.protein_number = data['protein_number']
    
    data = data_processing(data, args_config)
    data = k_fold(data, args_config)
    
    drdr_graph, didi_graph, data = dgl_similarity_graph(data, args_config)
    drdr_graph = drdr_graph.to(device)
    didi_graph = didi_graph.to(device)
    
    drug_feature = torch.FloatTensor(data['drugfeature']).to(device)
    disease_feature = torch.FloatTensor(data['diseasefeature']).to(device)
    protein_feature = torch.FloatTensor(data['proteinfeature']).to(device)
    
    if 'drug_topo_features' in data:
        drug_topo_feat = torch.FloatTensor(data['drug_topo_features']).to(device)
        disease_topo_feat = torch.FloatTensor(data['disease_topo_features']).to(device)
    
    drdipr_graph, data = dgl_heterograph(data, data['X_train'][0], args_config)
    drdipr_graph = drdipr_graph.to(device)
    
    model = AMNTDDA(args_config).to(device)
    
    model_path = os.path.join(args_config.result_dir, 'fold_0_best_model.pt')
    if os.path.exists(model_path):
        model.load_state_dict(torch.load(model_path, map_location=device))
        print(f"[AI] Loaded trained model from {model_path}")
    else:
        print(f"[AI] WARNING: No trained model found, using random weights")
    
    model.eval()
    print(f"[AI] Ready! Drugs={args_config.drug_number}, Diseases={args_config.disease_number}, Device={device}")


def predict_scores(pairs):
    sample = torch.LongTensor(pairs).to(device)
    with torch.no_grad():
        _, scores = model(drdr_graph, didi_graph, drdipr_graph,
                         drug_feature, disease_feature, protein_feature,
                         sample, drug_topo_feat, disease_topo_feat)
    probs = F.softmax(scores, dim=-1)
    return probs[:, 1].cpu().numpy()


@app.route('/predict/drug', methods=['POST'])
def predict_drug():
    """Drug → top-k diseases"""
    data_in = request.json
    drug_idx = data_in.get('drug_idx', 0)
    top_k = data_in.get('top_k', 10)
    
    if drug_idx < 0 or drug_idx >= args_config.drug_number:
        return jsonify({'error': f'drug_idx {drug_idx} out of range'}), 400
    
    pairs = [[drug_idx, j] for j in range(args_config.disease_number)]
    scores = predict_scores(pairs)
    sorted_idx = np.argsort(-scores)[:top_k]
    
    results = []
    for rank, idx in enumerate(sorted_idx):
        results.append({
            'rank': rank + 1,
            'disease_idx': int(idx),
            'score': float(round(scores[idx], 5)),
            'is_known': False
        })
    
    return jsonify({'predictions': results})


@app.route('/predict/disease', methods=['POST'])
def predict_disease():
    """Disease → top-k drugs"""
    data_in = request.json
    disease_idx = data_in.get('disease_idx', 0)
    top_k = data_in.get('top_k', 10)
    
    if disease_idx < 0 or disease_idx >= args_config.disease_number:
        return jsonify({'error': f'disease_idx {disease_idx} out of range'}), 400
    
    pairs = [[i, disease_idx] for i in range(args_config.drug_number)]
    scores = predict_scores(pairs)
    sorted_idx = np.argsort(-scores)[:top_k]
    
    results = []
    for rank, idx in enumerate(sorted_idx):
        results.append({
            'rank': rank + 1,
            'drug_idx': int(idx),
            'score': float(round(scores[idx], 5)),
            'is_known': False
        })
    
    return jsonify({'predictions': results})


@app.route('/health')
def health():
    return jsonify({
        'status': 'ok',
        'drugs': args_config.drug_number,
        'diseases': args_config.disease_number,
        'device': str(device)
    })


if __name__ == '__main__':
    load_model()
    print("=" * 60)
    print("[AI] AMDGT AI Server running on http://localhost:5001")
    print("[AI] PHP website sẽ gọi API vào đây")
    print("=" * 60)
    app.run(host='0.0.0.0', port=5001, debug=False)
