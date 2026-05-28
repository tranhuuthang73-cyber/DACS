"""
Improved Data Preprocessing for AMNTDDA.

Extends the baseline preprocessing with topological features computed from
similarity graphs. All original functions are imported unchanged from
data_preprocess.py to ensure fair comparison.

Topological features capture structural properties of the similarity graphs
that raw similarity vectors cannot represent (e.g., clustering structure,
spectral position, random walk properties).
"""

import numpy as np
import torch
import dgl
import networkx as nx

# Import ALL baseline functions (these remain unchanged)
from data_preprocess import (
    get_adj, k_matrix, get_data, data_processing,
    k_fold, dgl_similarity_graph, dgl_heterograph
)
from topo_utils import compute_topo_features


def dgl_heterograph_improved(data, drdi_pairs, args, mode="full", add_reverse_edges=False, include_protein_edges=True):
    """
    Improved-only heterograph builder (baseline remains untouched).

    Modes:
    - full: same as baseline dgl_heterograph (drug-disease edges from all drdi_pairs)
    - pos_only: only drug-disease positive edges (caller must pass positives)
    - pos_only_no_protein: only drug-disease positive edges, no protein relations
    """
    if mode == "full":
        # Delegate to baseline behavior for compatibility.
        return dgl_heterograph(data, drdi_pairs, args)

    if mode not in {"pos_only", "pos_only_no_protein"}:
        raise ValueError(f"Unknown heterograph mode: {mode}")

    # Drug-Disease edges (caller supplies positives for pos_only modes)
    drdi_list = [drdi_pairs[i] for i in range(drdi_pairs.shape[0])]
    drpr_list = [data["drpr"][i] for i in range(data["drpr"].shape[0])] if include_protein_edges else []
    dipr_list = [data["dipr"][i] for i in range(data["dipr"].shape[0])] if include_protein_edges else []

    node_dict = {
        "drug": args.drug_number,
        "disease": args.disease_number,
        "protein": args.protein_number,
    }

    heterograph_dict = {
        ("drug", "association", "disease"): drdi_list,
    }

    if mode != "pos_only_no_protein" and include_protein_edges:
        heterograph_dict[("drug", "association", "protein")] = drpr_list
        heterograph_dict[("disease", "association", "protein")] = dipr_list

    if add_reverse_edges:
        heterograph_dict[("disease", "rev_association", "drug")] = [(b, a) for (a, b) in drdi_list]
        if mode != "pos_only_no_protein" and include_protein_edges:
            heterograph_dict[("protein", "rev_association", "drug")] = [(b, a) for (a, b) in drpr_list]
            heterograph_dict[("protein", "rev_association", "disease")] = [(b, a) for (a, b) in dipr_list]

    data["feature_dict"] = {
        "drug": torch.tensor(data["drugfeature"]),
        "disease": torch.tensor(data["diseasefeature"]),
        "protein": torch.tensor(data["proteinfeature"]),
    }

    drdipr_graph = dgl.heterograph(heterograph_dict, num_nodes_dict=node_dict)
    return drdipr_graph, data


def dgl_similarity_graph_improved(data, args, pe_dim=16, rw_steps=None):
    """
    Extended version of dgl_similarity_graph that also computes
    topological features from the k-NN similarity graphs.
    
    Returns the same graphs as the baseline PLUS topological features
    for drug and disease nodes.
    
    Args:
        data: dict - data dictionary from data_processing()
        args: argparse namespace
        pe_dim: int - Laplacian PE dimension
        rw_steps: list - random walk steps for RWSE
    
    Returns:
        drdr_graph: DGL graph - drug-drug similarity graph
        didi_graph: DGL graph - disease-disease similarity graph
        drug_topo: (n_drugs, topo_dim) numpy array
        disease_topo: (n_diseases, topo_dim) numpy array
        data: dict - updated data dictionary
    """
    if rw_steps is None:
        rw_steps = [2, 4, 8, 16]
    
    # Step 1: Compute k-NN similarity matrices (same as baseline)
    drdr_matrix = k_matrix(data['drs'], args.neighbor)
    didi_matrix = k_matrix(data['dis'], args.neighbor)
    
    # Step 2: Create DGL graphs (same as baseline)
    drdr_nx = nx.from_numpy_array(drdr_matrix)
    didi_nx = nx.from_numpy_array(didi_matrix)
    drdr_graph = dgl.from_networkx(drdr_nx)
    didi_graph = dgl.from_networkx(didi_nx)
    
    drdr_graph.ndata['drs'] = torch.tensor(data['drs'])
    didi_graph.ndata['dis'] = torch.tensor(data['dis'])
    
    # Step 3: Compute topological features from k-NN matrices (NEW)
    print(f"  Computing drug topological features (pe_dim={pe_dim}, rw_steps={rw_steps})...")
    drug_topo = compute_topo_features(drdr_matrix, pe_dim=pe_dim, rw_steps=rw_steps)
    
    print(f"  Computing disease topological features...")
    disease_topo = compute_topo_features(didi_matrix, pe_dim=pe_dim, rw_steps=rw_steps)
    
    print(f"  Drug topo features: {drug_topo.shape}")
    print(f"  Disease topo features: {disease_topo.shape}")
    
    # Store in data dict for reference
    data['drug_topo'] = drug_topo
    data['disease_topo'] = disease_topo
    
    return drdr_graph, didi_graph, drug_topo, disease_topo, data
