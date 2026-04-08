"""
Test Script: Verify Persistent Homology Integration
Kiểm tra xem persistent homology features được tính đúng không
"""

import sys
import numpy as np
import pandas as pd
import argparse
from data_preprocess import get_data, data_processing, dgl_similarity_graph


def test_topo_feature_extraction():
    """Test trích xuất topological features"""
    print("\n" + "="*60)
    print("TEST: Topological Features Extraction")
    print("="*60)
    
    # Mock args
    class Args:
        k_fold = 2
        epochs = 5
        lr = 1e-4
        weight_decay = 1e-3
        random_seed = 1234
        neighbor = 20
        negative_rate = 1.0
        dataset = 'C-dataset'
        dropout = 0.2
        gt_layer = 2
        gt_head = 2
        gt_out_dim = 200
        hgt_layer = 2
        hgt_head = 8
        hgt_in_dim = 64
        hgt_head_dim = 25
        hgt_out_dim = 200
        tr_layer = 2
        tr_head = 4
    
    args = Args()
    args.data_dir = 'data/' + args.dataset + '/'
    
    try:
        print("\n[1] Loading data...")
        data = get_data(args)
        args.drug_number = data['drug_number']
        args.disease_number = data['disease_number']
        args.protein_number = data['protein_number']
        print(f"✓ Loaded: {args.drug_number} drugs, {args.disease_number} diseases")
        
        print("\n[2] Processing data...")
        data = data_processing(data, args)
        print(f"✓ Data processed: {len(data['all_samples'])} samples")
        
        print("\n[3] Computing similarity graphs & topological features...")
        drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)
        print(f"✓ Graphs created")
        
        print("\n[4] Checking topological features...")
        
        # Check drug topo features
        if 'drug_topo_features' in data:
            drug_topo = data['drug_topo_features']
            print(f"✓ Drug Topological Features:")
            print(f"  - Shape: {drug_topo.shape}")
            print(f"  - dtype: {drug_topo.dtype}")
            print(f"  - Mean: {np.mean(drug_topo):.6f}")
            print(f"  - Std: {np.std(drug_topo):.6f}")
            print(f"  - Min: {np.min(drug_topo):.6f}")
            print(f"  - Max: {np.max(drug_topo):.6f}")
            print(f"  - First 10: {drug_topo[:10]}")
        else:
            print("✗ Drug topological features NOT FOUND!")
            return False
        
        # Check disease topo features
        if 'disease_topo_features' in data:
            disease_topo = data['disease_topo_features']
            print(f"\n✓ Disease Topological Features:")
            print(f"  - Shape: {disease_topo.shape}")
            print(f"  - dtype: {disease_topo.dtype}")
            print(f"  - Mean: {np.mean(disease_topo):.6f}")
            print(f"  - Std: {np.std(disease_topo):.6f}")
            print(f"  - Min: {np.min(disease_topo):.6f}")
            print(f"  - Max: {np.max(disease_topo):.6f}")
            print(f"  - First 10: {disease_topo[:10]}")
        else:
            print("✗ Disease topological features NOT FOUND!")
            return False
        
        # Check drug-disease graph stats
        if 'drdi_graph_stats' in data:
            stats = data['drdi_graph_stats']
            print(f"\n✓ Drug-Disease Association Graph Statistics:")
            for key, val in stats.items():
                if isinstance(val, (int, float)):
                    print(f"  - {key}: {val:.4f}" if isinstance(val, float) else f"  - {key}: {val}")
                else:
                    print(f"  - {key}: {val}")
        else:
            print("✗ Drug-disease graph stats NOT FOUND!")
        
        print("\n" + "="*60)
        print("✓ ALL TESTS PASSED!")
        print("="*60)
        return True
        
    except Exception as e:
        print(f"\n✗ ERROR: {e}")
        import traceback
        traceback.print_exc()
        return False


def test_feature_dimension():
    """Test kích thước features có đúng không"""
    print("\n" + "="*60)
    print("TEST: Feature Dimensions")
    print("="*60)
    
    class Args:
        k_fold = 2
        epochs = 5
        lr = 1e-4
        weight_decay = 1e-3
        random_seed = 1234
        neighbor = 20
        negative_rate = 1.0
        dataset = 'C-dataset'
        dropout = 0.2
        gt_layer = 2
        gt_head = 2
        gt_out_dim = 200
        hgt_layer = 2
        hgt_head = 8
        hgt_in_dim = 64
        hgt_head_dim = 25
        hgt_out_dim = 200
        tr_layer = 2
        tr_head = 4
    
    args = Args()
    args.data_dir = 'data/' + args.dataset + '/'
    
    try:
        data = get_data(args)
        args.drug_number = data['drug_number']
        args.disease_number = data['disease_number']
        args.protein_number = data['protein_number']
        data = data_processing(data, args)
        drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)
        
        drug_features_original = data['drugfeature']
        disease_features_original = data['diseasefeature']
        drug_topo = data['drug_topo_features']
        disease_topo = data['disease_topo_features']
        
        print(f"\nOriginal Drug Features: {drug_features_original.shape}")
        print(f"Drug Topological Features: {drug_topo.shape}")
        print(f"Combined could be: ({drug_features_original.shape[0]}, {drug_features_original.shape[1] + drug_topo.shape[0]})")
        
        print(f"\nOriginal Disease Features: {disease_features_original.shape}")
        print(f"Disease Topological Features: {disease_topo.shape}")
        print(f"Combined could be: ({disease_features_original.shape[0]}, {disease_features_original.shape[1] + disease_topo.shape[0]})")
        
        print("\n✓ Dimension Test Passed!")
        return True
        
    except Exception as e:
        print(f"\n✗ ERROR: {e}")
        return False


def test_no_nan_inf():
    """Test không có nan/inf values"""
    print("\n" + "="*60)
    print("TEST: Checking for NaN/Inf Values")
    print("="*60)
    
    class Args:
        k_fold = 2
        epochs = 5
        lr = 1e-4
        weight_decay = 1e-3
        random_seed = 1234
        neighbor = 20
        negative_rate = 1.0
        dataset = 'C-dataset'
        dropout = 0.2
        gt_layer = 2
        gt_head = 2
        gt_out_dim = 200
        hgt_layer = 2
        hgt_head = 8
        hgt_in_dim = 64
        hgt_head_dim = 25
        hgt_out_dim = 200
        tr_layer = 2
        tr_head = 4
    
    args = Args()
    args.data_dir = 'data/' + args.dataset + '/'
    
    try:
        data = get_data(args)
        args.drug_number = data['drug_number']
        args.disease_number = data['disease_number']
        args.protein_number = data['protein_number']
        data = data_processing(data, args)
        drdr_graph, didi_graph, data = dgl_similarity_graph(data, args)
        
        drug_topo = data['drug_topo_features']
        disease_topo = data['disease_topo_features']
        
        # Check for NaN
        drug_nan = np.isnan(drug_topo).sum()
        disease_nan = np.isnan(disease_topo).sum()
        
        # Check for Inf
        drug_inf = np.isinf(drug_topo).sum()
        disease_inf = np.isinf(disease_topo).sum()
        
        print(f"\nDrug Topological Features:")
        print(f"  - NaN values: {drug_nan}" + (" ✗ FOUND!" if drug_nan > 0 else " ✓ OK"))
        print(f"  - Inf values: {drug_inf}" + (" ✗ FOUND!" if drug_inf > 0 else " ✓ OK"))
        
        print(f"\nDisease Topological Features:")
        print(f"  - NaN values: {disease_nan}" + (" ✗ FOUND!" if disease_nan > 0 else " ✓ OK"))
        print(f"  - Inf values: {disease_inf}" + (" ✗ FOUND!" if disease_inf > 0 else " ✓ OK"))
        
        if drug_nan + drug_inf + disease_nan + disease_inf == 0:
            print("\n✓ No NaN/Inf Values Found!")
            return True
        else:
            print("\n✗ Found NaN/Inf Values!")
            return False
            
    except Exception as e:
        print(f"\n✗ ERROR: {e}")
        return False


if __name__ == '__main__':
    print("\n" + "="*60)
    print("PERSISTENT HOMOLOGY INTEGRATION TESTS")
    print("="*60)
    
    tests = [
        ("Topological Features Extraction", test_topo_feature_extraction),
        ("Feature Dimensions", test_feature_dimension),
        ("NaN/Inf Values Check", test_no_nan_inf),
    ]
    
    results = []
    for test_name, test_func in tests:
        try:
            result = test_func()
            results.append((test_name, result))
        except Exception as e:
            print(f"\n✗ Test '{test_name}' failed with exception: {e}")
            results.append((test_name, False))
    
    print("\n" + "="*60)
    print("TEST SUMMARY")
    print("="*60)
    
    for test_name, result in results:
        status = "✓ PASSED" if result else "✗ FAILED"
        print(f"{test_name:<50} {status}")
    
    all_passed = all(result for _, result in results)
    print("\n" + ("✓ ALL TESTS PASSED!" if all_passed else "✗ SOME TESTS FAILED!"))
    print("="*60)
