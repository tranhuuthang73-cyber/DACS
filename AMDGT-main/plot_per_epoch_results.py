import argparse
import os

import matplotlib.pyplot as plt
import pandas as pd


PLOT_ITEMS = [
    ("auc", "AUC Progress", "AUC", "#6ea6be"),
    ("aupr", "AUPR Progress", "AUPR", "#b76a99"),
    ("accuracy", "Accuracy Progress", "Accuracy", "#e7a73a"),
    ("precision", "Precision Progress", "Precision", "#d46a48"),
    ("recall", "Recall Progress", "Recall", "#7da56c"),
    ("f1", "F1-Score Progress", "F1-Score", "#c86a6d"),
]


def plot_fold_metrics(metrics_csv, dataset, fold, output_png, max_epoch=None, show_plot=False):
    metrics_df = pd.read_csv(metrics_csv)
    if metrics_df.empty:
        raise ValueError(f"No data found in {metrics_csv}")

    if max_epoch is not None:
        metrics_df = metrics_df[metrics_df["epoch"] <= max_epoch]
        if metrics_df.empty:
            raise ValueError(f"No rows left after filtering max_epoch={max_epoch}")

    x = metrics_df["epoch"].to_numpy()
    fig, axes = plt.subplots(2, 3, figsize=(12, 8))
    fig.suptitle(f"Per-Epoch Results - {dataset} - Fold {fold}", fontsize=15, fontweight="bold")

    for ax, (col, title, ylabel, color) in zip(axes.flatten(), PLOT_ITEMS):
        y = metrics_df[col].to_numpy()
        ax.plot(x, y, color=color, linewidth=1.8)
        ax.fill_between(x, y, color=color, alpha=0.35)
        ax.set_title(title, fontsize=11, fontweight="bold")
        ax.set_xlabel("Epoch")
        ax.set_ylabel(ylabel)
        ax.grid(alpha=0.25)

    plt.tight_layout(rect=[0, 0, 1, 0.95])
    plt.savefig(output_png, dpi=220)
    if show_plot:
        plt.show()
    else:
        plt.close(fig)


def main():
    parser = argparse.ArgumentParser(description="Plot per-epoch 2x3 metrics for one fold.")
    parser.add_argument("--dataset", default="C-dataset", help="dataset name")
    parser.add_argument("--result_dir", default=None, help="path to result dir (default: Result/<dataset>/AMNTDDA)")
    parser.add_argument("--fold", type=int, default=0, help="fold index")
    parser.add_argument("--max_epoch", type=int, default=25, help="max epoch to display (default: 25)")
    parser.add_argument("--show_plot", action="store_true", help="show plot window")
    args = parser.parse_args()

    result_dir = args.result_dir or os.path.join("Result", args.dataset, "AMNTDDA")
    metrics_csv = os.path.join(result_dir, f"fold_{args.fold}", "metrics.csv")
    output_png = os.path.join(result_dir, f"fold_{args.fold}", "per_epoch_2x3.png")

    if not os.path.exists(metrics_csv):
        raise FileNotFoundError(f"metrics.csv not found: {metrics_csv}")

    plot_fold_metrics(
        metrics_csv=metrics_csv,
        dataset=args.dataset,
        fold=args.fold,
        output_png=output_png,
        max_epoch=args.max_epoch,
        show_plot=args.show_plot,
    )
    print(f"Saved: {output_png}")


if __name__ == "__main__":
    main()
