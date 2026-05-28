import torch
import sys
import platform

def check_environment():
    print("="*50)
    print("AMDGT ENVIRONMENT DIAGNOSTIC")
    print("="*50)
    
    print(f"Python version: {sys.version}")
    print(f"Platform: {platform.platform()}")
    print("-" * 50)
    
    print(f"PyTorch version: {torch.__version__}")
    
    cuda_available = torch.cuda.this_is_available() if hasattr(torch.cuda, 'this_is_available') else torch.cuda.is_available()
    print(f"CUDA available: {cuda_available}")
    
    if cuda_available:
        print(f"CUDA version: {torch.version.cuda}")
        print(f"Device count: {torch.cuda.device_count()}")
        print(f"Device name: {torch.cuda.get_device_name(0)}")
    else:
        print("\n[!] WARNING: CUDA is NOT available for PyTorch.")
        print("To enable GPU acceleration, please install the CUDA-enabled version of PyTorch.")
        print("Command: pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118")
    
    print("="*50)

if __name__ == "__main__":
    check_environment()
