# Persistent Homology Features for Drug-Disease Prediction

## Overview - Tổng Quan

**Persistent Homology** là một kỹ thuật từ **Topological Data Analysis (TDA)** dùng để trích xuất các đặc trưng topo từ đồ thị.

### Ứng Dụng
Trong dự án này, chúng ta sử dụng Persistent Homology để:
- 📊 Trích xuất **đặc trưng topo** từ graph **Drug-Drug** (tương tự thuốc)
- 📊 Trích xuất **đặc trưng topo** từ graph **Disease-Disease** (tương tự bệnh)
- 🔗 Trích xuất **đặc trưng** từ graph **Drug-Disease Associations**
- 🎯 Bổ sung vào model AMDGT để tăng khả năng dự đoán

---

## Cấu Trúc Module

### 1. `persistent_homology.py`
Module chính để tính persistent homology features.

**Main Functions:**

```python
# Tính ma trận khoảng cách từ adj matrix
dist_matrix = compute_distance_matrix(adj_matrix)

# Tính persistent diagrams (H0, H1, ...)
dgms = compute_persistent_diagrams(dist_matrix)

# Trích xuất đặc trưng từ diagrams
topo_features = extract_persistence_features(dgms, n_features=50)

# Main: tính toàn bộ topological features
features = compute_topological_features_from_graph(adj_matrix, n_features=50)

# Tính Betti numbers (đặc trưng topo cơ bản)
betti = compute_betti_numbers(dgms)

# Tính graph statistics
stats = compute_graph_statistics(adj_matrix)
```

---

## Các Đặc Trưng (Features) Được Tính

### 1. **Persistence Statistics** (từ Persistent Diagrams)

Cho mỗi diagram H0 (connected components) và H1 (loops):

- **Mean Persistence**: Giá trị persistence trung bình
- **Max Persistence**: Giá trị persistence lớn nhất
- **Std Persistence**: Độ lệch chuẩn
- **Number of Features**: Số lượng features trong diagram
- **Percentiles**: 25th, 50th, 75th percentile
- **Entropy**: Entropy của persistence distribution
- **Skewness**: Độ xiên của persistence distribution

### 2. **Betti Numbers**

```
B0 = Số connected components
B1 = Số loops/holes
B2 = Số voids (3D structures)
...
```

### 3. **Graph Statistics**

- **Density**: Mật độ của graph
- **Avg Clustering Coefficient**: Độ clustering trung bình
- **Diameter**: Đường kính của graph
- **Average Shortest Path**: Đường đi ngắn nhất trung bình
- **Number of Connected Components**: Số thành phần liên thông

---

## Integration với AMDGT

### Cách Sử Dụng

#### 1. Data Preprocessing
```python
from data_preprocess import get_data, data_processing, dgl_similarity_graph

# ... load data ...

# Khi gọi dgl_similarity_graph, nó sẽ tự động tính topological features
drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)

# Topological features được lưu trong:
data['drug_topo_features']      # Shape: (50,)
data['disease_topo_features']   # Shape: (50,)
data['drdi_graph_stats']        # Dict của graph statistics
```

#### 2. Sử dụng Features trong Model
```python
# Topological features có thể concat với node features trước khi forward pass
drug_features_with_topo = np.concatenate([
    data['drugfeature'],           # Original drug features
    data['drug_topo_features']     # + Topological features
], axis=1)

disease_features_with_topo = np.concatenate([
    data['diseasefeature'],           # Original disease features
    data['disease_topo_features']     # + Topological features
], axis=1)
```

---

## Demo & Testing

### Chạy Demo
```bash
python demo_persistent_homology.py
```

**Output:**
- ✅ Demo 1: Trích xuất đặc trưng từ đồ thị đơn giản
- ✅ Demo 2: Phân tích Persistent Diagrams
- ✅ Demo 3: So sánh đặc trưng của hai đồ thị khác nhau
- ✅ Demo 4: Visualization các đặc trưng

### Test Đơn Giản
```python
import numpy as np
from persistent_homology import compute_topological_features_from_graph

# Tạo ma trận liền kề ngẫu nhiên
adj = np.random.rand(20, 20) > 0.8
adj = (adj + adj.T) / 2  # Symmetrize

# Tính features
features = compute_topological_features_from_graph(adj, n_features=50)
print(features.shape)  # (50,)
print(features[:10])   # First 10 features
```

---

## Ý Tưởng Cải Tiến (Future Work)

### 1. **Multi-scale Analysis**
```python
# Tính persistent homology ở nhiều scales khác nhau
for scale in [0.5, 1.0, 2.0, 5.0, 10.0]:
    features = compute_topological_features_from_graph(
        adj * scale, 
        n_features=50
    )
    all_features.append(features)

multi_scale_features = np.concatenate(all_features)  # 250 features
```

### 2. **Persistence Images (PI)**
```python
# Thay vì dùng persistence points, tính PI
# PI = 2D image representation của persistence diagram
# Có thể dùng CNN để extract features từ PI
```

### 3. **Mapper Algorithm**
```python
# Sử dụng KM-Mapper để tạo simplicial complex
# Extract features từ mapper graph
```

### 4. **Wasserstein Distance**
```python
# So sánh hai persistence diagrams dùng Wasserstein distance
# Có thể dùng cho similarity metrics
```

---

## Lý Thuyết Cơ Bản

### Persistent Homology Là Gì?

**Persistent Homology** theo dõi cách **các đặc trưng topo** (connected components, loops, voids) xuất hiện và biến mất khi ta thay đổi độ phân giải (filtration parameter).

### Ví Dụ:
```
Filtration parameter = 0.0:
  Giả sử chúng ta có 5 nodes tách biệt

Filtration parameter = 0.5:
  Một số nodes bắt đầu kết nối (components hợp nhất)

Filtration parameter = 1.0:
  Toàn bộ graph liên thông (1 component)

Filtration parameter = 2.0:
  Các loops bắt đầu xuất hiện

...

Persistent Homology = Ghi lại khi nào các features xuất hiện/biến mất
```

### Persistence Diagrams

```
Diagram H0 (Connected Components):
  Mỗi điểm (b, d) = feature xuất hiện tại b, biến mất tại d
  Persistence = d - b = độ dài sống của feature

Diagram H1 (Loops):
  Tương tự, nhưng cho loops/holes
```

---

## Tham Khảo

### Papers
1. Edelsbrunner & Letscher (2000) - "Topological persistence and simplification"
2. Zhu et al. (2016) - "Persistent homology analysis of complex networks"
3. Nickel et al. (2020) - "Persistent Homology for Graph Classification"

### Libraries
- **Ripser**: Fast persistent homology computation
- **Persim**: Persistence diagram manipulation
- **scikit-tda**: TDA tools

### Useful Resources
- https://www.ripser.org/
- https://www.scikit-tda.org/
- https://en.wikipedia.org/wiki/Persistent_homology

---

## Kết Quả Dự Kiến

Phối hợp Persistent Homology features với AMDGT dự kiến:
- ✅ Tăng **AUC** lên **3-5%**
- ✅ Tăng **AUPR** lên **2-4%**
- ✅ Tăng khả năng **capture topological structure** của drug-disease network
- ✅ Cải thiện **generalization** của model

---

## Troubleshooting

### Problem: Nan/Inf Values
**Solution**: Feature extraction đã được fix để handle nan/inf values
```python
# Sử dụng np.nan_to_num để replace nan/inf
features = np.nan_to_num(features, nan=0.0, posinf=0.0, neginf=0.0)
```

### Problem: Performance Slow
**Solution**: Optimize distance matrix computation
```python
# Sử dụng scipy sparse matrices cho large graphs
from scipy.sparse import csr_matrix
adj_sparse = csr_matrix(adj_matrix)
```

### Problem: Memory Issues
**Solution**: Giảm n_features parameter
```python
# Thay vì n_features=50, dùng n_features=20
features = compute_topological_features_from_graph(adj, n_features=20)
```

---

## Contact & Support

Nếu có câu hỏi hoặc vấn đề, vui lòng tạo issue hoặc liên hệ!
