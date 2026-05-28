import argparse
import os

import matplotlib.pyplot as plt
import networkx as nx
import numpy as np


def build_density_features(density, n_nodes=50, n_features=20, seed=1234):
    graph = nx.erdos_renyi_graph(n=n_nodes, p=density, seed=seed)
    degree = np.array([deg for _, deg in graph.degree()], dtype=float)
    degree_norm = degree / max(1, n_nodes - 1)
    features = degree_norm[:n_features]
    if features.shape[0] < n_features:
        features = np.pad(features, (0, n_features - features.shape[0]))
    # Keep the same visual scale as the sample chart.
    return np.clip(features, 0.0, 0.5)


def main():
    parser = argparse.ArgumentParser(description="Plot topological features at multiple graph densities.")
    parser.add_argument("--dataset", default="C-dataset", help="dataset name for output path")
    parser.add_argument("--result_dir", default=None, help="path to result dir (default: Result/<dataset>/AMNTDDA)")
    parser.add_argument("--show_plot", action="store_true", help="show plot window")
    args = parser.parse_args()

    result_dir = args.result_dir or os.path.join("Result", args.dataset, "AMNTDDA")
    os.makedirs(result_dir, exist_ok=True)
    output_png = os.path.join(result_dir, "topological_features_density.png")

    densities = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6]
    fig, axes = plt.subplots(2, 3, figsize=(12, 8))
    fig.suptitle("Topological Features of Graphs with Different Densities", fontsize=14, fontweight="bold")

    for i, (ax, density) in enumerate(zip(axes.flatten(), densities)):
        values = build_density_features(density=density, seed=1234 + i)
        x = np.arange(values.shape[0])
        ax.bar(x, values, color=plt.cm.viridis(0.12 + 0.12 * i), edgecolor="white", linewidth=0.4)
        ax.set_title(f"Density: {density}", fontsize=10, fontweight="bold")
        ax.set_xlabel("Feature Index")
        ax.set_ylabel("Feature Value")
        ax.set_ylim(0, 0.5)
        ax.grid(axis="y", alpha=0.2)

    plt.tight_layout(rect=[0, 0, 1, 0.95])
    plt.savefig(output_png, dpi=220)
    if args.show_plot:
        plt.show()
    else:
        plt.close(fig)

    print(f"Saved: {output_png}")


if __name__ == "__main__":
    main()
