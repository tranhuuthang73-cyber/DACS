"""
Demo: Sử dụng Persistent Homology Features trong AMDGT
Hiển thị cách integrate topological features vào model
"""

import numpy as np
import matplotlib.pyplot as plt
from persistent_homology import (
    compute_topological_features_from_graph,
    compute_graph_statistics,
    compute_persistent_diagrams,
    compute_distance_matrix,
    compute_betti_numbers
)

def demo_basic_topo_features():
    """Demo cơ bản: tính topological features từ một đồ thị"""
    print("\n" + "="*60)
    print("DEMO 1: Basic Topological Features")
    print("="*60)
    
    # Tạo ví dụ: ma trận liền kề biểu diễn drug-disease associations
    # 5 drugs, 5 diseases
    adj_matrix = np.array([
        [0, 1, 0, 1, 1],
        [1, 0, 1, 0, 1],
        [0, 1, 0, 1, 0],
        [1, 0, 1, 0, 1],
        [1, 1, 0, 1, 0]
    ], dtype=float)
    
    print("\nInput Adjacency Matrix (5x5):")
    print(adj_matrix)
    
    # Tính topological features
    topo_features = compute_topological_features_from_graph(adj_matrix, n_features=30)
    
    print(f"\nTopological Features (n_features=30):")
    print(f"Shape: {topo_features.shape}")
    print(f"First 10 features: {topo_features[:10]}")
    print(f"Statistics - Min: {topo_features.min():.4f}, Max: {topo_features.max():.4f}, Mean: {topo_features.mean():.4f}")
    
    # Tính graph statistics
    stats = compute_graph_statistics(adj_matrix)
    print(f"\nGraph Statistics:")
    for key, value in stats.items():
        if isinstance(value, float):
            print(f"  {key}: {value:.4f}")
        else:
            print(f"  {key}: {value}")


def demo_persistent_diagrams():
    """Demo: Hiển thị persistent diagrams"""
    print("\n" + "="*60)
    print("DEMO 2: Persistent Diagrams Analysis")
    print("="*60)
    
    # Tạo một mạng phức tạp hơn
    np.random.seed(42)
    n_nodes = 20
    adj_matrix = np.zeros((n_nodes, n_nodes))
    
    # Tạo một số clusters
    for i in range(5):
        cluster_nodes = list(range(i*4, (i+1)*4))
        for n1 in cluster_nodes:
            for n2 in cluster_nodes:
                if n1 != n2 and np.random.rand() > 0.3:
                    adj_matrix[n1, n2] = 1
    
    # Thêm một số edges giữa clusters
    for i in range(0, n_nodes-1, 2):
        if np.random.rand() > 0.7:
            adj_matrix[i, i+1] = 1
    
    # Symmetrize
    adj_matrix = (adj_matrix + adj_matrix.T) / 2
    adj_matrix = (adj_matrix > 0).astype(float)
    
    print(f"\nTest Network: {n_nodes} nodes, Density: {np.sum(adj_matrix) / (n_nodes**2):.4f}")
    
    # Tính distance matrix
    dist_matrix = compute_distance_matrix(adj_matrix)
    print(f"Distance Matrix computed, shape: {dist_matrix.shape}")
    
    # Tính persistent diagrams
    dgms = compute_persistent_diagrams(dist_matrix)
    
    print(f"\nPersistent Diagrams (H0, H1, ...):")
    for idx, dgm in enumerate(dgms):
        print(f"  H{idx}: {len(dgm)} features")
        if len(dgm) > 0:
            persistence = dgm[:, 1] - dgm[:, 0]
            persistence = persistence[persistence > 1e-10]
            if len(persistence) > 0:
                print(f"    Persistence: min={persistence.min():.4f}, max={persistence.max():.4f}, mean={persistence.mean():.4f}")
    
    # Tính Betti numbers
    betti = compute_betti_numbers(dgms)
    print(f"\nBetti Numbers: {betti}")
    print(f"  B0 (Connected Components): {betti[0]}")
    if len(betti) > 1:
        print(f"  B1 (Loops/Holes): {betti[1]}")


def demo_compare_graphs():
    """Demo: So sánh features của hai đồ thị khác nhau"""
    print("\n" + "="*60)
    print("DEMO 3: Comparing Two Different Graphs")
    print("="*60)
    
    # Graph 1: Nhân tạo (structured)
    print("\nGraph 1: Regular Grid (Structured)")
    n = 6
    adj1 = np.zeros((n*n, n*n))
    for i in range(n):
        for j in range(n):
            idx = i*n + j
            if j < n-1:
                adj1[idx, idx+1] = 1
                adj1[idx+1, idx] = 1
            if i < n-1:
                adj1[idx, idx+n] = 1
                adj1[idx+n, idx] = 1
    
    # Graph 2: Random
    print("Graph 2: Random Network")
    adj2 = np.zeros((n*n, n*n))
    np.random.seed(42)
    for i in range(n*n):
        for j in range(i+1, n*n):
            if np.random.rand() < 0.15:
                adj2[i, j] = 1
                adj2[j, i] = 1
    
    # Tính features
    feat1 = compute_topological_features_from_graph(adj1, n_features=20)
    feat2 = compute_topological_features_from_graph(adj2, n_features=20)
    
    # So sánh
    print(f"\nFeatures Comparison (first 10):")
    print(f"{'Feature':<30} {'Graph1':<15} {'Graph2':<15}")
    print("-" * 60)
    for i in range(10):
        print(f"Feature {i:<20} {feat1[i]:<15.6f} {feat2[i]:<15.6f}")
    
    # Euclidean distance giữa feature vectors
    distance = np.linalg.norm(feat1 - feat2)
    print(f"\nEuclidean Distance between feature vectors: {distance:.6f}")
    
    # Graph stats
    stat1 = compute_graph_statistics(adj1)
    stat2 = compute_graph_statistics(adj2)
    
    print(f"\nGraph 1 (Structured) Stats:")
    for k, v in stat1.items():
        if isinstance(v, (int, float)):
            print(f"  {k}: {v:.4f}" if isinstance(v, float) else f"  {k}: {v}")
    
    print(f"\nGraph 2 (Random) Stats:")
    for k, v in stat2.items():
        if isinstance(v, (int, float)):
            print(f"  {k}: {v:.4f}" if isinstance(v, float) else f"  {k}: {v}")


def visualize_features():
    """Visualize topological features"""
    print("\n" + "="*60)
    print("DEMO 4: Visualizing Features")
    print("="*60)
    
    # Tạo nhiều đồ thị với mức độ kết nối khác nhau
    fig, axes = plt.subplots(2, 3, figsize=(15, 10))
    fig.suptitle('Topological Features of Graphs with Different Densities', fontsize=14, fontweight='bold')
    
    densities = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6]
    features_list = []
    
    for idx, density in enumerate(densities):
        ax = axes[idx // 3, idx % 3]
        
        # Tạo random graph
        n = 20
        adj = np.zeros((n, n))
        np.random.seed(42 + idx)
        for i in range(n):
            for j in range(i+1, n):
                if np.random.rand() < density:
                    adj[i, j] = 1
                    adj[j, i] = 1
        
        # Tính features
        feat = compute_topological_features_from_graph(adj, n_features=20)
        features_list.append(feat)
        
        # Plot feature vector
        x = np.arange(len(feat))
        ax.bar(x, feat, color=plt.cm.viridis(density))
        ax.set_title(f'Density: {density:.1f}', fontweight='bold')
        ax.set_xlabel('Feature Index')
        ax.set_ylabel('Feature Value')
        ax.set_ylim([0, 0.5])
    
    plt.tight_layout()
    plt.savefig('topological_features_visualization.png', dpi=150, bbox_inches='tight')
    print("✓ Saved: topological_features_visualization.png")
    plt.show()


if __name__ == '__main__':
    print("\n" + "="*60)
    print("PERSISTENT HOMOLOGY FEATURES DEMO")
    print("="*60)
    
    # Chạy các demos
    demo_basic_topo_features()
    demo_persistent_diagrams()
    demo_compare_graphs()
    
    # Visualize
    try:
        visualize_features()
    except Exception as e:
        print(f"Visualization skipped: {e}")
    
    print("\n" + "="*60)
    print("✓ ALL DEMOS COMPLETED!")
    print("="*60)
    print("\nNow you can integrate these topological features into your AMDGT model!")
