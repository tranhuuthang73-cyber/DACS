# GPU Optimization Settings - Paste vào đầu file train_DDA_improved.py

# Enable TF32 on Ampere GPUs for faster training (must be after torch import)
if torch.cuda.is_available():
    torch.backends.cuda.matmul.allow_tf32 = True
    torch.backends.cudnn.allow_tf32 = True
    torch.backends.cudnn.benchmark = True  # Auto-tune for fixed input size
    torch.backends.cudnn.deterministic = False  # Faster but non-deterministic
    # Force use GPU 0 (in case multiple GPUs)
    torch.cuda.set_device(0)
    print(f"[*] GPU: {torch.cuda.get_device_name(0)}")
    print(f"[*] GPU Memory: {torch.cuda.get_device_properties(0).total_memory / 1e9:.1f} GB")

# Set environment for better GPU performance
os.environ['CUDA_LAUNCH_BLOCKING'] = '0'
os.environ['CUDA_DEVICE_ORDER'] = 'PCI_BUS_ID'

# Limit CPU threads to avoid CPU bottleneck
torch.set_num_threads(4)  # Use 4 threads for data preprocessing
np.random.seed(0)
torch.manual_seed(0)

# Helper function để convert GPU tensor sang CPU
def _to_cpu(tensor):
    """Convert GPU tensor to numpy for sklearn metrics"""
    if isinstance(tensor, torch.Tensor):
        return tensor.detach().cpu().numpy()
    return tensor
