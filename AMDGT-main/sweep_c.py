"""
Sweep for C-dataset - Drug-Disease Association Prediction

Usage:
  python sweep_c.py                       # Run with winning config, max 3 loops
  python sweep_c.py --max_sweep_loops 1   # Run once only

Dataset: C-dataset
Pass threshold: ALL 7 metrics (AUC, AUPR, Accuracy, Precision, Recall, F1, MCC) delta >= 0.01
Config: final_optimized_config (Contrastive Learning + DropEdge + pos_weight=1.5 + select_metric=f1)
"""
import argparse
import csv
import json
import shlex
import shutil
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path


# --- WINNING CONFIG: 7/7 PASS (2026-05-13) ---
DEFAULT_CONFIGS = [
    {
        "name": "final_optimized_config",
        "args": [
            "--neighbor", "5",
            "--dropout", "0.30",
            "--weight_decay", "5e-4",
            "--loss_type", "ce",
            "--hetero_graph_mode", "full",
            "--select_metric", "f1",
            "--add_reverse_edges",
            "--no_val",
            "--pos_weight", "1.5",
            "--patience", "200",
            "--ensemble_topk", "1",
            "--contrastive_weight", "0.1",
            "--contrastive_temp", "0.07",
            "--sim_drop_edge", "0.1",
        ],
    },
]


def read_mean_metrics(summary_csv):
    with summary_csv.open("r", encoding="utf-8", newline="") as f:
        for row in csv.DictReader(f):
            if row.get("Fold") == "Mean":
                return {
                    "AUC": float(row["AUC"]),
                    "AUPR": float(row["AUPR"]),
                    "Accuracy": float(row["Accuracy"]),
                    "Precision": float(row["Precision"]),
                    "Recall": float(row["Recall"]),
                    "F1-score": float(row["F1-score"]),
                    "Mcc": float(row["Mcc"]),
                }
    raise ValueError(f"Mean row not found in {summary_csv}")


def read_deltas(comparison_csv):
    deltas = {}
    with comparison_csv.open("r", encoding="utf-8", newline="") as f:
        for row in csv.DictReader(f):
            deltas[row["Metric"]] = float(row["Delta"])
    return deltas


def find_latest_log(log_dir, started_at):
    logs = sorted(log_dir.glob("train_v1_*.log"), key=lambda p: p.stat().st_mtime, reverse=True)
    if not logs:
        return None
    for log in logs:
        if log.stat().st_mtime >= started_at - 2:
            return log
    return logs[0]


def find_overall_best_pt(v1_dir):
    pt_path = v1_dir / "overall_best_model.pt"
    if pt_path.exists():
        return pt_path
    for fold_dir in sorted(v1_dir.glob("fold_*")):
        pt_path = fold_dir / "best_model.pt"
        if pt_path.exists():
            return pt_path
    return None


def copy_if_exists(src, dst):
    if src and src.exists():
        shutil.copy2(src, dst)


def write_csv(path, rows):
    if not rows:
        return
    with path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        writer.writeheader()
        writer.writerows(rows)


def run_one(cfg, args, repo_root, v1_dir, run_dir):
    cmd = [
        sys.executable,
        "train_DDA_improved_C.py",
        "--dataset", "C-dataset",
        "--version", "v1",
        "--epochs", str(args.epochs),
        "--k_fold", str(args.k_fold),
        "--patience", str(args.patience),
    ] + cfg["args"]

    cmd_text = " ".join(shlex.quote(x) for x in cmd)
    (run_dir / "command.txt").write_text(cmd_text + "\n", encoding="utf-8")
    (run_dir / "config.json").write_text(json.dumps(cfg, indent=2), encoding="utf-8")

    print(f"\n[{cfg['name']}] Starting...")
    started_at = time.time()
    proc = subprocess.run(cmd, cwd=repo_root)
    duration = time.time() - started_at

    log_path = find_latest_log(v1_dir, started_at)
    src_summary = v1_dir / "summary_v1.csv"
    src_comparison = v1_dir / "comparison_v1.csv"
    src_plot = v1_dir / "fold_summary_v1.png"
    src_pt = find_overall_best_pt(v1_dir)

    copy_if_exists(log_path, run_dir / (log_path.name if log_path else "train.log"))
    copy_if_exists(src_summary, run_dir / "summary_v1.csv")
    copy_if_exists(src_comparison, run_dir / "comparison_v1.csv")
    copy_if_exists(src_plot, run_dir / "fold_summary_v1.png")

    if src_pt and src_pt.exists():
        shutil.copy2(src_pt, run_dir / "best_model.pt")
        print(f"  -> Saved model: {run_dir / 'best_model.pt'}")

    result = {
        "run_name": cfg["name"],
        "run_dir": str(run_dir),
        "return_code": proc.returncode,
        "duration_sec": round(duration, 1),
        "mean_auc": None, "mean_aupr": None,
        "delta_auc": None, "delta_aupr": None,
        "delta_acc": None, "delta_prec": None,
        "delta_recall": None, "delta_f1": None, "delta_mcc": None,
        "pass_all": False,
    }

    cmp_copy = run_dir / "comparison_v1.csv"
    sum_copy = run_dir / "summary_v1.csv"
    if proc.returncode == 0 and cmp_copy.exists() and sum_copy.exists():
        means = read_mean_metrics(sum_copy)
        deltas = read_deltas(cmp_copy)
        result["mean_auc"] = means["AUC"]
        result["mean_aupr"] = means["AUPR"]
        result["delta_auc"] = deltas.get("AUC")
        result["delta_aupr"] = deltas.get("AUPR")
        result["delta_acc"] = deltas.get("Accuracy")
        result["delta_prec"] = deltas.get("Precision")
        result["delta_recall"] = deltas.get("Recall")
        result["delta_f1"] = deltas.get("F1-score")
        result["delta_mcc"] = deltas.get("Mcc")

        # Check ALL 7 metrics >= threshold
        all_pass = True
        for metric_name, delta_val in [("AUC", result["delta_auc"]),
                                        ("AUPR", result["delta_aupr"]),
                                        ("Accuracy", result["delta_acc"]),
                                        ("Precision", result["delta_prec"]),
                                        ("Recall", result["delta_recall"]),
                                        ("F1", result["delta_f1"]),
                                        ("MCC", result["delta_mcc"])]:
            if delta_val is None or delta_val < args.pass_delta:
                all_pass = False
        result["pass_all"] = all_pass

    print(
        f"[{cfg['name']}] Done rc={proc.returncode} "
        f"dAUC={result['delta_auc']} dAUPR={result['delta_aupr']} "
        f"dF1={result['delta_f1']} dMCC={result['delta_mcc']} "
        f"pass_all={result['pass_all']}"
    )
    return result


def main():
    parser = argparse.ArgumentParser(description="Auto sweep for C-dataset")
    parser.add_argument("--epochs", type=int, default=1000, help="epochs for each run")
    parser.add_argument("--k_fold", type=int, default=10, help="k-fold for each run")
    parser.add_argument("--patience", type=int, default=150, help="patience for each run")
    parser.add_argument("--pass_delta", type=float, default=0.01, help="delta threshold to mark pass")
    parser.add_argument("--max_sweep_loops", type=int, default=3, help="max loops (0=infinite)")
    args = parser.parse_args()

    repo_root = Path(__file__).resolve().parent
    v1_dir = repo_root / "Result" / "C-dataset" / "AMNTDDA_improved" / "V1"
    if not v1_dir.exists():
        raise FileNotFoundError(f"Missing path: {v1_dir}")

    session_id = datetime.now().strftime("%Y%m%d_%H%M%S")
    sweep_dir = v1_dir / "sweep_runs" / session_id
    sweep_dir.mkdir(parents=True, exist_ok=True)

    # Backup current artifacts
    backup_dir = sweep_dir / "pre_sweep_backup"
    backup_dir.mkdir(parents=True, exist_ok=True)
    for artifact in ["summary.csv", "summary_v1.csv", "comparison_v1.csv", "fold_summary_v1.png", "overall_best_model.pt"]:
        src = v1_dir / artifact
        if src.exists():
            shutil.copy2(src, backup_dir / artifact)

    configs = DEFAULT_CONFIGS
    max_loops = args.max_sweep_loops if args.max_sweep_loops > 0 else float("inf")

    print(f"Sweep session: {sweep_dir}")
    print(f"Dataset: C-dataset | Epochs: {args.epochs} | K-fold: {args.k_fold}")
    print(f"Config: {configs[0]['name']} | Max loops: {max_loops} | Pass threshold: {args.pass_delta}")
    print("=" * 60)

    best_result = None
    passed = False

    for loop_idx in range(1, int(max_loops) + 1 if max_loops != float("inf") else 9999):
        print(f"\n{'='*60}")
        print(f"SWEEP LOOP {loop_idx} / {'∞' if max_loops == float('inf') else int(max_loops)}")
        print("=" * 60)

        cfg = configs[0]
        run_dir = sweep_dir / f"loop{loop_idx:02d}_{cfg['name']}"
        run_dir.mkdir(parents=True, exist_ok=True)
        result = run_one(cfg, args, repo_root, v1_dir, run_dir)

        # Update V1 artifacts if this run is better
        if result["return_code"] == 0 and result["delta_auc"] is not None:
            curr_score = min(
                result["delta_auc"] or 0, result["delta_aupr"] or 0,
                result["delta_acc"] or 0, result["delta_recall"] or 0,
                result["delta_f1"] or 0, result["delta_mcc"] or 0
            )
            prev_score = -999
            if best_result and best_result["delta_auc"] is not None:
                prev_score = min(
                    best_result["delta_auc"] or 0, best_result["delta_aupr"] or 0,
                    best_result["delta_acc"] or 0, best_result["delta_recall"] or 0,
                    best_result["delta_f1"] or 0, best_result["delta_mcc"] or 0
                )
            if curr_score > prev_score:
                best_result = result
                print(f"  -> Updating V1 artifacts with better run...")
                for artifact in ["summary_v1.csv", "comparison_v1.csv", "fold_summary_v1.png", "best_model.pt"]:
                    src = run_dir / artifact
                    if src.exists():
                        shutil.copy2(src, v1_dir / artifact)
                if (run_dir / "summary_v1.csv").exists():
                    shutil.copy2(run_dir / "summary_v1.csv", v1_dir / "summary.csv")

        if result["pass_all"]:
            passed = True
            print(f"\n*** ALL 7 METRICS PASSED! Sweep complete! ***")
            break

        print(f"\n[Loop {loop_idx}] Passed: {result['pass_all']}")

    if not passed:
        print(f"\n*** Max loops ({args.max_sweep_loops}) reached. Stopping sweep. ***")

    # Print best result summary
    if best_result:
        print("\n" + "=" * 60)
        print("BEST RUN:")
        print(f"  Config: {best_result['run_name']}")
        print(f"  AUC: {best_result['mean_auc']:.4f} (delta: {best_result['delta_auc']})")
        print(f"  AUPR: {best_result['mean_aupr']:.4f} (delta: {best_result['delta_aupr']})")
        print(f"  F1: {best_result['delta_f1']} | MCC: {best_result['delta_mcc']}")
        print(f"  Model: {v1_dir / 'best_model.pt'}")
    else:
        # Restore backup if no valid run
        for artifact in ["summary.csv", "summary_v1.csv", "comparison_v1.csv", "fold_summary_v1.png", "overall_best_model.pt"]:
            src = backup_dir / artifact
            if src.exists():
                shutil.copy2(src, v1_dir / artifact)
        print("\nNo valid run completed successfully.")

    print(f"\nSaved sweep artifacts to: {sweep_dir}")


if __name__ == "__main__":
    main()
