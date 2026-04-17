# Run B-dataset and F-dataset training sequentially

$baseCmd = "python train_DDA.py --epochs 1000 --k_fold 10 --neighbor 20 --lr 0.0005 --weight_decay 0.0001 --hgt_layer 3 --hgt_in_dim 128"

Write-Host "Starting B-dataset training..."
Invoke-Expression "$baseCmd --dataset B-dataset"

Write-Host "Starting F-dataset training..."
Invoke-Expression "$baseCmd --dataset F-dataset"

Write-Host "All training complete!"
