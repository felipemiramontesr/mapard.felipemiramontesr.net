import os
import sys
import subprocess
import platform
import urllib.request
import tempfile
import logging
import time

class PdfConverter:
    """Handles Markdown to PDF conversion using Pandoc and LaTeX.
    
    This class manages the complex dependency chain (Pandoc, MiKTeX) and 
    orchestrates document compilation with automatic fallback strategies
    for missing drivers or template errors.
    """

    def __init__(self) -> None:
        self.logger = logging.getLogger("PdfConverter")
        logging.basicConfig(level=logging.INFO)
        # Standard MiKTeX user install path
        self.miktex_user_path = os.path.join(os.environ.get("LOCALAPPDATA", ""), "Programs", "MiKTeX", "miktex", "bin", "x64")
        self.template_name = "mapa-rd.tex"

    def _check_pandoc(self):
        """Checks if pandoc is available in the PATH."""
        try:
            subprocess.run(["pandoc", "--version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
            return True
        except FileNotFoundError:
            return False

    def _update_path_env(self):
        """Updates os.environ PATH to include likely new binary locations."""
        if platform.system() == "Windows":
             # Add MiKTeX User Path
             if os.path.exists(self.miktex_user_path):
                 os.environ["PATH"] += os.pathsep + self.miktex_user_path
                 self.logger.info(f"[*] Temporarily added MiKTeX to PATH: {self.miktex_user_path}")

    def _install_pandoc(self):
        """Attempts to install pandoc based on OS."""
        system = platform.system()
        print(f"[*] Pandoc not found. Attempting automatic installation for {system}...")
        
        try:
            if system == "Windows":
                url = "https://github.com/jgm/pandoc/releases/download/3.6.3/pandoc-3.6.3-windows-x86_64.msi" 
                temp_dir = tempfile.gettempdir()
                msi_path = os.path.join(temp_dir, "pandoc_install.msi")
                
                print(f"[*] Downloading Pandoc MSI from {url}...")
                urllib.request.urlretrieve(url, msi_path) # nosec
                
                print("[*] Running silent installation (requires privileges)...")
                cmd = ["msiexec", "/i", msi_path, "/quiet", "/norestart"]
                subprocess.run(cmd, check=True)
                print("[*] Pandoc installation command finished.")
                
            elif system == "Linux":
                if os.geteuid() != 0:
                    print("[!] Not running as root. Cannot install pandoc via apt.")
                    return False
                print("[*] Running apt-get install pandoc...")
                subprocess.run(["apt-get", "update"], check=False)
                subprocess.run(["apt-get", "install", "-y", "pandoc"], check=True)
                
            elif system == "Darwin":
                print("[*] Running brew install pandoc...")
                subprocess.run(["brew", "install", "pandoc"], check=True)
                
            else:
                print(f"[!] Unsupported OS for auto-install: {system}")
                return False
                
            return True
            
        except Exception as e:
            print(f"[!] Pandoc auto-installation failed: {e}")
            return False

    def _install_miktex(self):
        """Attempts to install MiKTeX Basic on Windows (User mode)."""
        if platform.system() != "Windows":
            return False
            
        print("[*] PDF Engine (LaTeX) appears missing. Attempting MiKTeX Basic installation (Windows Unattended)...")
        try:
            # URL for MiKTeX Basic Installer (Net Installer or Basic Installer)
            # Using a reliable mirror or the main site redirection
            url = "https://miktex.org/download/ctan/systems/win32/miktex/setup/windows-x64/basic-miktex-24.1-x64.exe"
            temp_dir = tempfile.gettempdir()
            installer_path = os.path.join(temp_dir, "miktex_installer.exe")
            
            print(f"[*] Downloading MiKTeX Installer from {url}...")
            # Note: User request implies generic latest if possible, but hardcoded version is safer for script stability.
            # Using the exact version 24.1 as of early 2024/2025 context.
            urllib.request.urlretrieve(url, installer_path) # nosec
            
            print("[*] Running MiKTeX silent install (Current User, Basic Package Set)...")
            # Flags: --private (user), --unattended (no ui), --package-set=basic
            cmd = [installer_path, "--private", "--unattended", "--package-set=basic"]
            subprocess.run(cmd, check=True)
            
            print("[*] MiKTeX installation finished.")
            
            # Attempt to set auto-install-missing-packages = yes using initexmf
            # We need to find the binary first. It should be in LOCALAPPDATA.
            initexmf_path = os.path.join(self.miktex_user_path, "initexmf.exe")
            if os.path.exists(initexmf_path):
                 print("[*] Configuring MiKTeX to install missing packages on-the-fly...")
                 subprocess.run([initexmf_path, "--set-config-value", "[MPM]AutoInstall=1"], check=False)
            
            self._update_path_env()
            return True
            
        except Exception as e:
            print(f"[!] MiKTeX auto-installation failed: {e}")
            return False

    def convert_to_pdf(self, md_path):
        """
        Converts a given MD file to PDF.
        Returns: (success: bool, output_path: str, error_msg: str)
        """
        if not os.path.exists(md_path):
            return False, None, "Markdown file not found"

        pdf_path = md_path.replace(".md", ".pdf")
        
        # 1. Strategy: Try Convert -> If Fail, Analyze -> Install -> Retry
        
        # Ensure Pandoc
        if not self._check_pandoc():
            if not self._install_pandoc():
                return False, None, "Pandoc missing and install failed"
            # Refresh check
            if not self._check_pandoc():
                 return False, None, "Pandoc installed but not in PATH components (restart required)"

        # Template Path
        template_name = self.template_name
        template_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '08_Templates', template_name)
        
        has_template = os.path.exists(template_path)
        
        def run_conversion(use_template=True):
            # Basic pandoc command
            # Using --pdf-engine=miktex (path) is hard, better rely on PATH
            # We add potential paths before running
            self._update_path_env()
            
            cmd = ["pandoc", md_path, "-o", pdf_path,
                   "--from=markdown+raw_tex+pipe_tables+grid_tables+fenced_divs"]
            
            # Template Injection
            if use_template and has_template:
                cmd.extend(["--template", template_path])
            
            return subprocess.run(
                cmd, 
                stdout=subprocess.PIPE, 
                stderr=subprocess.PIPE,
                check=True # Raise CalledProcessError on fail
            )

        try:
            print(f"[*] Converting {md_path} to PDF (Attempt 1 - Main Template)...")
            run_conversion(use_template=True)
            print(f"[+] PDF successfully generated: {pdf_path}")
            return True, pdf_path, None
            
        except subprocess.CalledProcessError as e:
            err_output = e.stderr.decode('utf-8', errors='ignore')
            print(f"[!] PDF Conversion Failed (Attempt 1).")
            print(f"    Command: {e.cmd}")
            print(f"    Return Code: {e.returncode}")
            print(f"    Stderr: \n{err_output}\n")
            
            # Analyze Error
            missing_latex = any(k in err_output for k in ["pdflatex not found", "xelatex not found", "lualatex not found", "pdf-engine is missing"])
            template_issue = "template" in err_output or "LaTeX Error" in err_output or "Undefined control sequence" in err_output
            
            # Strategy A: MiKTeX Install (if engine missing)
            if missing_latex and platform.system() == "Windows":
                print("[!] LaTeX engine missing. Attempting MiKTeX install...")
                if self._install_miktex():
                    # Retry
                    try:
                        print(f"[*] Converting {md_path} to PDF (Attempt 2 - Post MiKTeX Install)...")
                        run_conversion(use_template=True)
                        print(f"[+] PDF successfully generated (after install): {pdf_path}")
                        return True, pdf_path, None
                    except subprocess.CalledProcessError as e2:
                        err2 = e2.stderr.decode('utf-8', errors='ignore')
                        print(f"[!] PDF Conversion Failed (Attempt 2). Stderr: {err2}")
                        return False, None, f"Conversion failed after MiKTeX install: {err2}"
                else:
                    return False, None, "LaTeX missing and MiKTeX install failed/skipped"
            
            # Strategy B: Fallback Template (if template failed)
            # We CANNOT run without a template because the MD has injected custom LaTeX environments (\begin{execsummary}).
            # Running plain pandoc would cause Exit 43 (Undefined control sequence).
            # We must use a 'safety' template that defines these as no-ops.
            
            fallback_template_name = "fallback.tex"
            fallback_template_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '08_Templates', fallback_template_name)
            
            if (template_issue or e.returncode == 43) and os.path.exists(fallback_template_path):
                 print("[!] Detailed branding failed. Retrying with SAFE FALLBACK template...")
                 try:
                     # Direct subprocess run to force the fallback template
                     cmd = ["pandoc", md_path, "-o", pdf_path, "--template", fallback_template_path]
                     self._update_path_env()
                     
                     subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
                     
                     print(f"[+] PDF generated (Fallback: Minimal Design): {pdf_path}")
                     return True, pdf_path, "Warning: Main template failed, used fallback."
                 except subprocess.CalledProcessError as e3:
                     err3 = e3.stderr.decode('utf-8', errors='ignore')
                     print(f"[!] Fallback Failed. Stderr: {err3}")
                     return False, None, f"Fallback conversion failed: {err3}"

            return False, None, f"Pandoc error: {err_output}"
                
        except Exception as e:
            # Capture unexpected python exceptions
            print(f"[!] Unexpected Python Exception in PDF Converter: {e}")
            return False, None, f"Unexpected error: {e}"
