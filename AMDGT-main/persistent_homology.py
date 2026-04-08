"""
Persistent Homology Module - Trích xuất đặc trưng topo từ đồ thị Drug-Disease
Sử dụng Ego-Network Persistent Homology trên đồ thị bipartite Drug-Disease

Phương pháp:
  1. Xây dựng đồ thị bipartite Drug-Disease (drugs ↔ diseases)
  2. Với mỗi drug/disease, lấy ego-network k-hop
  3. Tính Persistent Homology (Ripser) trên ego-network
  4. Trích xuất feature vector (50-dim) cho mỗi node

→ Mỗi drug và disease có vector đặc trưng topo RIÊNG BIỆT
"""

import numpy as np
from scipy.stats import entropy
import networkx as nx
from ripser import ripser
import warnings
warnings.filterwarnings('ignore')


# ============================================================
# CORE FUNCTIONS: Distance Matrix & Persistence Diagrams
# ============================================================

def compute_distance_matrix(adj_matrix):
    """
    Tính ma trận khoảng cách từ ma trận liền kề
    Sử dụng shortest path distance
    """
    G = nx.from_numpy_array(adj_matrix, create_using=nx.Graph())
    n = adj_matrix.shape[0]
    dist_matrix = np.full((n, n), 0.0)
    
    path_lengths = dict(nx.all_pairs_shortest_path_length(G))
    
    max_dist = n + 1
    for i in range(n):
        for j in range(n):
            if i == j:
                dist_matrix[i, j] = 0
            elif j in path_lengths.get(i, {}):
                dist_matrix[i, j] = path_lengths[i][j]
            else:
                dist_matrix[i, j] = max_dist
    
    return dist_matrix


def compute_distance_matrix_from_graph(G):
    """
    Tính ma trận khoảng cách trực tiếp từ NetworkX graph
    Tối ưu hơn compute_distance_matrix vì không cần chuyển đổi adj→graph
    
    Args:
        G: NetworkX graph (có thể có node IDs không liên tục)
    Returns:
        dist_matrix: (n, n) numpy array
        node_list: danh sách node IDs theo thứ tự trong matrix
    """
    node_list = sorted(G.nodes())
    n = len(node_list)
    node_to_idx = {node: idx for idx, node in enumerate(node_list)}
    
    max_dist = n + 1
    dist_matrix = np.full((n, n), max_dist, dtype=float)
    np.fill_diagonal(dist_matrix, 0)
    
    try:
        path_lengths = dict(nx.all_pairs_shortest_path_length(G))
        for src in path_lengths:
            if src in node_to_idx:
                i = node_to_idx[src]
                for tgt, dist in path_lengths[src].items():
                    if tgt in node_to_idx:
                        dist_matrix[i, node_to_idx[tgt]] = dist
    except Exception:
        pass
    
    return dist_matrix, node_list


def compute_persistent_diagrams(dist_matrix):
    """
    Tính Persistent Diagrams sử dụng Ripser
    Trả về diagram cho H0 (connected components) và H1 (loops/cycles)
    """
    result = ripser(dist_matrix, coeff=2, do_cocycles=False)
    dgms = result['dgms']
    return dgms


def extract_persistence_features(dgms, n_features=50):
    """
    Trích xuất đặc trưng từ persistence diagrams
    
    Đặc trưng cho mỗi homology dimension (H0, H1):
      1. Mean persistence
      2. Max persistence
      3. Std persistence
      4. Number of features (count)
      5-7. Percentiles (25, 50, 75)
      8. Persistence entropy
      9. Skewness
    
    Args:
        dgms: List of persistence diagrams [H0, H1]
        n_features: Số đặc trưng cần trích xuất (default: 50)
    
    Returns:
        features: Vector đặc trưng topo (n_features,)
    """
    features = []
    
    for dgm_idx, dgm in enumerate(dgms):
        if len(dgm) == 0:
            features.extend([0] * (n_features // 2))
            continue
        
        # Tính persistence (death - birth)
        persistence = dgm[:, 1] - dgm[:, 0]
        persistence = persistence[(persistence > 1e-10) & (persistence < np.inf)]
        
        if len(persistence) == 0:
            features.extend([0] * (n_features // 2))
            continue
        
        # 1. Mean persistence
        mean_pers = np.mean(persistence)
        features.append(mean_pers if np.isfinite(mean_pers) else 0)
        
        # 2. Max persistence
        max_pers = np.max(persistence)
        features.append(max_pers if np.isfinite(max_pers) else 0)
        
        # 3. Std persistence
        std_pers = np.std(persistence) if len(persistence) > 1 else 0
        features.append(std_pers if np.isfinite(std_pers) else 0)
        
        # 4. Number of persistent features
        features.append(len(persistence))
        
        # 5-7. Percentiles
        for p in [25, 50, 75]:
            perc = np.percentile(persistence, p)
            features.append(perc if np.isfinite(perc) else 0)
        
        # 8. Persistence entropy
        pers_sum = np.sum(persistence)
        if pers_sum > 0:
            pers_normalized = persistence / pers_sum
            pers_norm_nonzero = pers_normalized[pers_normalized > 1e-10]
            if len(pers_norm_nonzero) > 0:
                ent = entropy(pers_norm_nonzero)
                features.append(ent if np.isfinite(ent) else 0)
            else:
                features.append(0)
        else:
            features.append(0)
        
        # 9. Skewness
        if len(persistence) > 1:
            mean_p = np.mean(persistence)
            std_p = np.std(persistence)
            if std_p > 1e-10:
                skewness = np.mean((persistence - mean_p) ** 3) / (std_p ** 3)
                features.append(skewness if np.isfinite(skewness) else 0)
            else:
                features.append(0)
        else:
            features.append(0)
    
    # Pad hoặc trim để có đúng n_features
    if len(features) >= n_features:
        features = np.array(features[:n_features])
    else:
        features = np.pad(features, (0, n_features - len(features)))
    
    features = np.nan_to_num(features, nan=0.0, posinf=0.0, neginf=0.0)
    
    return features


# ============================================================
# EGO-NETWORK PH: Tính PH cho từng node trong đồ thị Drug-Disease
# ============================================================

def compute_ego_network_ph(G, node, k_hop=2, n_features=50):
    """
    Tính Persistent Homology cho ego-network của 1 node trong đồ thị Drug-Disease
    
    Ego-network = subgraph gồm tất cả nodes trong phạm vi k-hop từ node trung tâm
    
    Ý nghĩa topology:
      - H0 (connected components): số cụm rời rạc trong vùng lân cận
      - H1 (loops/cycles): chu trình drug→disease→drug→disease→... quanh node
    
    Args:
        G: NetworkX graph (bipartite Drug-Disease)
        node: node index trong graph
        k_hop: bán kính ego-network (default: 2)
        n_features: số chiều feature vector
    
    Returns:
        features: (n_features,) array — đặc trưng topo của node này
    """
    try:
        # Lấy ego-network (subgraph k-hop quanh node)
        ego = nx.ego_graph(G, node, radius=k_hop)
        
        # Nếu ego-network quá nhỏ (≤2 nodes), không đủ cấu trúc để tính PH
        if ego.number_of_nodes() <= 2:
            return np.zeros(n_features, dtype=np.float32)
        
        # Giới hạn kích thước ego-network để tránh tính toán nặng
        if ego.number_of_nodes() > 150:
            bfs_nodes = [node]
            visited = {node}
            queue = [node]
            while len(bfs_nodes) < 150 and queue:
                current = queue.pop(0)
                for neighbor in G.neighbors(current):
                    if neighbor not in visited:
                        visited.add(neighbor)
                        bfs_nodes.append(neighbor)
                        queue.append(neighbor)
                        if len(bfs_nodes) >= 150:
                            break
            ego = G.subgraph(bfs_nodes).copy()
        
        # Tính distance matrix trực tiếp trên ego subgraph
        dist_matrix, _ = compute_distance_matrix_from_graph(ego)
        
        # Tính Persistent Homology bằng Ripser
        dgms = compute_persistent_diagrams(dist_matrix)
        
        # Trích xuất features
        features = extract_persistence_features(dgms, n_features=n_features)
        
        return features.astype(np.float32)
    
    except Exception as e:
        return np.zeros(n_features, dtype=np.float32)


def compute_node_level_topo_features(drdi_edges, n_drugs, n_diseases, k_hop=2, n_features=50):
    """
    ★ HÀM CHÍNH: Trích xuất đặc trưng topo (Persistent Homology) từ đồ thị Drug-Disease
    
    Pipeline:
      1. Xây dựng đồ thị bipartite Drug-Disease
      2. Với MỖI drug → lấy ego-network → tính PH → vector feature riêng
      3. Với MỖI disease → lấy ego-network → tính PH → vector feature riêng
    
    Args:
        drdi_edges: array [[drug_idx, disease_idx], ...] (positive associations)
        n_drugs: số thuốc (663)
        n_diseases: số bệnh (409)
        k_hop: bán kính ego-network (default: 2)
        n_features: chiều vector feature (default: 50)
    
    Returns:
        drug_topo_features: (n_drugs, n_features) — mỗi drug 1 vector riêng
        disease_topo_features: (n_diseases, n_features) — mỗi disease 1 vector riêng
    """
    # ===== Bước 1: Xây dựng đồ thị bipartite Drug-Disease =====
    G = nx.Graph()
    # Drug nodes: index 0 → n_drugs-1
    # Disease nodes: index n_drugs → n_drugs+n_diseases-1
    G.add_nodes_from(range(n_drugs + n_diseases))
    
    for edge in drdi_edges:
        drug_idx, disease_idx = int(edge[0]), int(edge[1])
        G.add_edge(drug_idx, n_drugs + disease_idx)
    
    print(f"  [PH] Drug-Disease bipartite graph: {G.number_of_nodes()} nodes, {G.number_of_edges()} edges")
    print(f"  [PH] Avg drug degree: {2*G.number_of_edges()/n_drugs:.1f}, Avg disease degree: {2*G.number_of_edges()/n_diseases:.1f}")
    print(f"  [PH] Ego-network radius: k={k_hop}")
    
    # ===== Bước 2: Tính PH cho mỗi drug =====
    drug_features = np.zeros((n_drugs, n_features), dtype=np.float32)
    print(f"  [PH] Computing ego-network PH for {n_drugs} drugs...")
    for i in range(n_drugs):
        drug_features[i] = compute_ego_network_ph(G, i, k_hop, n_features)
        if (i + 1) % 100 == 0 or i == 0:
            print(f"    Drug {i+1}/{n_drugs} done")
    
    # ===== Bước 3: Tính PH cho mỗi disease =====
    disease_features = np.zeros((n_diseases, n_features), dtype=np.float32)
    print(f"  [PH] Computing ego-network PH for {n_diseases} diseases...")
    for j in range(n_diseases):
        node_id = n_drugs + j
        disease_features[j] = compute_ego_network_ph(G, node_id, k_hop, n_features)
        if (j + 1) % 100 == 0 or j == 0:
            print(f"    Disease {j+1}/{n_diseases} done")
    
    # ===== Thống kê =====
    drug_nonzero = np.sum(np.any(drug_features != 0, axis=1))
    disease_nonzero = np.sum(np.any(disease_features != 0, axis=1))
    print(f"  [PH] Drug topo features: shape={drug_features.shape}, non-zero nodes: {drug_nonzero}/{n_drugs}")
    print(f"  [PH] Disease topo features: shape={disease_features.shape}, non-zero nodes: {disease_nonzero}/{n_diseases}")
    
    # Verify: các drug/disease khác nhau phải có features khác nhau
    if n_drugs > 1:
        unique_drug = len(set([tuple(f) for f in drug_features]))
        print(f"  [PH] Unique drug feature vectors: {unique_drug}/{n_drugs}")
    
    return drug_features, disease_features


# ============================================================
# UTILITY FUNCTIONS
# ============================================================

def compute_topological_features_from_graph(adj_matrix, n_features=50):
    """
    Tính topological features từ ma trận liền kề (graph-level)
    Giữ lại cho backward compatibility
    """
    try:
        dist_matrix = compute_distance_matrix(adj_matrix)
        dgms = compute_persistent_diagrams(dist_matrix)
        topo_features = extract_persistence_features(dgms, n_features=n_features)
        return topo_features.astype(np.float32)
    except Exception as e:
        print(f"Lỗi khi tính topological features: {e}")
        return np.zeros(n_features, dtype=np.float32)


def compute_graph_statistics(adj_matrix):
    """
    Tính các thống kê topological cơ bản của đồ thị
    """
    G = nx.from_numpy_array(adj_matrix, create_using=nx.Graph())
    
    stats = {
        'num_nodes': G.number_of_nodes(),
        'num_edges': G.number_of_edges(),
        'density': nx.density(G),
        'num_connected_components': nx.number_connected_components(G),
        'avg_clustering': nx.average_clustering(G),
    }
    
    if nx.is_connected(G):
        stats['diameter'] = nx.diameter(G)
        stats['avg_shortest_path'] = nx.average_shortest_path_length(G)
    else:
        largest_cc = max(nx.connected_components(G), key=len)
        G_sub = G.subgraph(largest_cc)
        stats['diameter'] = nx.diameter(G_sub)
        stats['avg_shortest_path'] = nx.average_shortest_path_length(G_sub)
    
    return stats


def compute_betti_numbers(dgms):
    """Tính Betti numbers từ persistence diagrams"""
    betti_numbers = []
    for dgm in dgms:
        if len(dgm) == 0:
            betti_numbers.append(0)
        else:
            threshold = 0.01 * np.max(dgm[:, 1] - dgm[:, 0]) if len(dgm) > 0 else 0
            betti = np.sum(dgm[:, 1] - dgm[:, 0] > threshold)
            betti_numbers.append(betti)
    return np.array(betti_numbers)


# ============================================================
# TEST
# ============================================================

if __name__ == '__main__':
    print("=" * 60)
    print("Testing Ego-Network PH on Drug-Disease Graph")
    print("=" * 60)
    
    # Test nhỏ: 3 drugs, 4 diseases
    test_edges = np.array([
        [0, 0], [0, 1],   # Drug 0 → Disease 0, 1
        [1, 1], [1, 2],   # Drug 1 → Disease 1, 2
        [2, 2], [2, 3],   # Drug 2 → Disease 2, 3
    ])
    
    drug_feats, disease_feats = compute_node_level_topo_features(
        test_edges, n_drugs=3, n_diseases=4, k_hop=2, n_features=50
    )
    
    print(f"\nDrug features shape: {drug_feats.shape}")
    print(f"Disease features shape: {disease_feats.shape}")
    
    print(f"\nDrug 0 features[:5]: {drug_feats[0, :5]}")
    print(f"Drug 1 features[:5]: {drug_feats[1, :5]}")
    print(f"Drug 2 features[:5]: {drug_feats[2, :5]}")
    
    # Verify: drugs khác nhau phải có features khác nhau
    same_01 = np.allclose(drug_feats[0], drug_feats[1])
    same_02 = np.allclose(drug_feats[0], drug_feats[2])
    print(f"\nDrug 0 == Drug 1? {same_01}")
    print(f"Drug 0 == Drug 2? {same_02}")
    print(f"✓ Node-level features are unique: {not same_01 or not same_02}")
