#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
import sys
import json
import hashlib
import logging
import argparse
import subprocess
import fnmatch
import platform
import shutil
import importlib.util
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Tuple, Optional, Set, Any, Union
from dataclasses import dataclass, field, asdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from multiprocessing import cpu_count
from collections import defaultdict

# ═══════════════════════════════════════════════════════════════════════════════
# PLATFORMA ANIQLASH VA KUTUBXONALARNI BOSHQARISH
# ═══════════════════════════════════════════════════════════════════════════════

def detect_platform() -> Dict[str, Any]:
    system = platform.system().lower()
    is_android = False
    if hasattr(sys, 'getandroidapilevel'):
        is_android = True
        system = 'android'
    elif 'ANDROID_STORAGE' in os.environ or 'ANDROID_ROOT' in os.environ:
        is_android = True
        system = 'android'
    return {
        'system': system,
        'is_windows': system == 'windows',
        'is_linux': system == 'linux',
        'is_mac': system == 'darwin',
        'is_android': is_android,
        'is_pydroid': is_android,
        'machine': platform.machine(),
        'processor': platform.processor(),
        'python_version': sys.version,
        'python_implementation': platform.python_implementation(),
    }

PLATFORM_INFO = detect_platform()

REQUIRED_PACKAGES = {
    'pyfiglet': {'min_version': '0.8', 'install_name': 'pyfiglet'},
    'termcolor': {'min_version': '1.0', 'install_name': 'termcolor'},
    'chardet': {'min_version': '4.0', 'install_name': 'chardet'},
}

OPTIONAL_PACKAGES = {
    'psutil': {'min_version': '5.0', 'install_name': 'psutil', 'purpose': 'Tizim statistikasi'},
    'colorama': {'min_version': '0.4', 'install_name': 'colorama', 'purpose': 'Windows rangli chiqish'},
    'yaml': {'min_version': '5.0', 'install_name': 'pyyaml', 'purpose': 'YAML format support'},
}

def check_package(package_name: str) -> Tuple[bool, Optional[str]]:
    try:
        spec = importlib.util.find_spec(package_name)
        if spec is None:
            return False, None
        module = importlib.import_module(package_name)
        version = getattr(module, '__version__', 'unknown')
        return True, version
    except ImportError:
        return False, None

def install_package(package_name: str, install_name: str) -> bool:
    try:
        print(f"  📦 {package_name} o'rnatilmoqda...")
        if PLATFORM_INFO['is_pydroid']:
            import pip
            pip.main(['install', install_name])
        else:
            subprocess.check_call(
                [sys.executable, '-m', 'pip', 'install', '-q', install_name],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL
            )
        return True
    except Exception as e:
        print(f"  ❌ {package_name} o'rnatishda xato: {e}")
        return False

def check_and_install_dependencies(auto_install: bool = True) -> Tuple[bool, Dict[str, Any]]:
    print("\n🔧 KUTUBXONALAR TEKSHIRILMOQDA...")
    print("═" * 50)
    missing_packages = []
    installed_packages = {}

    for pkg_name, pkg_info in REQUIRED_PACKAGES.items():
        installed, version = check_package(pkg_name)
        if installed:
            installed_packages[pkg_name] = version
            print(f"  ✅ {pkg_name} v{version}")
        else:
            missing_packages.append((pkg_name, pkg_info['install_name']))
            print(f"  ❌ {pkg_name} topilmadi")

    for pkg_name, pkg_info in OPTIONAL_PACKAGES.items():
        installed, version = check_package(pkg_name)
        if installed:
            print(f"  🔵 {pkg_name} v{version} ({pkg_info['purpose']})")

    if missing_packages and auto_install:
        print("\n📦 O'RNATILMAGAN KUTUBXONALAR O'RNATILMOQDA...")
        for pkg_name, install_name in missing_packages:
            if install_package(pkg_name, install_name):
                installed_packages[pkg_name] = 'just_installed'
                print(f"  ✅ {pkg_name} muvaffaqiyatli o'rnatildi")
            else:
                print(f"\n❌ {pkg_name} ni o'rnatib bo'lmadi!")
                return False, {}

    return len(missing_packages) == 0 or (auto_install and missing_packages), installed_packages

DEPS_OK, INSTALLED_DEPS = check_and_install_dependencies(auto_install=True)
if not DEPS_OK:
    print("\n❌ Kerakli kutubxonalarni o'rnatib bo'lmadi!")
    print("   Qo'lda: pip3 install pyfiglet termcolor chardet")
    sys.exit(1)

try:
    import pyfiglet
    from termcolor import colored
    import chardet
except ImportError as e:
    print(f"❌ Kutubxonalarni import qilishda xato: {e}")
    sys.exit(1)

# YAML support (ixtiyoriy)
HAS_YAML = False
try:
    import yaml
    HAS_YAML = True
except ImportError:
    HAS_YAML = False

PSUTIL_AVAILABLE = False
try:
    import psutil
    if PLATFORM_INFO['is_android']:
        try:
            psutil.cpu_count()
            PSUTIL_AVAILABLE = True
        except:
            PSUTIL_AVAILABLE = False
    else:
        PSUTIL_AVAILABLE = True
except ImportError:
    PSUTIL_AVAILABLE = False

if PLATFORM_INFO['is_windows']:
    try:
        import colorama
        colorama.init()
    except ImportError:
        pass

# ═══════════════════════════════════════════════════════════════════════════════
# TIZIM STATISTIKASI
# ═══════════════════════════════════════════════════════════════════════════════

@dataclass
class SystemStats:
    os_name: str = ""
    os_version: str = ""
    os_arch: str = ""
    hostname: str = ""
    cpu_count_physical: int = 0
    cpu_count_logical: int = 0
    cpu_freq_current: float = 0.0
    cpu_freq_max: float = 0.0
    cpu_percent: float = 0.0
    ram_total: int = 0
    ram_available: int = 0
    ram_used: int = 0
    ram_percent: float = 0.0
    swap_total: int = 0
    swap_used: int = 0
    swap_percent: float = 0.0
    disk_total: int = 0
    disk_used: int = 0
    disk_free: int = 0
    disk_percent: float = 0.0
    disk_partitions: List[Dict] = field(default_factory=list)
    network_interfaces: List[str] = field(default_factory=list)
    python_version: str = ""
    python_compiler: str = ""
    python_implementation: str = ""
    process_pid: int = 0
    process_memory: int = 0
    is_android: bool = False
    android_api_level: int = 0

def get_system_stats() -> SystemStats:
    stats = SystemStats()
    stats.os_name = platform.system()
    stats.os_version = platform.version()
    stats.os_arch = platform.machine()
    stats.hostname = platform.node()
    stats.is_android = PLATFORM_INFO['is_android']
    if stats.is_android:
        try:
            stats.android_api_level = sys.getandroidapilevel() if hasattr(sys, 'getandroidapilevel') else 0
        except:
            pass
    stats.python_version = platform.python_version()
    stats.python_compiler = platform.python_compiler()
    stats.python_implementation = platform.python_implementation()
    stats.process_pid = os.getpid()

    if PSUTIL_AVAILABLE:
        try:
            try:
                stats.cpu_count_physical = psutil.cpu_count(logical=False) or 0
            except:
                pass
            try:
                stats.cpu_count_logical = psutil.cpu_count(logical=True) or cpu_count()
            except:
                stats.cpu_count_logical = cpu_count()
            try:
                cpu_freq = psutil.cpu_freq()
                if cpu_freq:
                    stats.cpu_freq_current = cpu_freq.current or 0.0
                    stats.cpu_freq_max = cpu_freq.max or 0.0
            except:
                pass
            try:
                stats.cpu_percent = psutil.cpu_percent(interval=0.1) or 0.0
            except:
                pass
            try:
                mem = psutil.virtual_memory()
                stats.ram_total = mem.total
                stats.ram_available = mem.available
                stats.ram_used = mem.used
                stats.ram_percent = mem.percent
            except:
                pass
            try:
                swap = psutil.swap_memory()
                stats.swap_total = swap.total
                stats.swap_used = swap.used
                stats.swap_percent = swap.percent
            except:
                pass
            try:
                if stats.is_android:
                    disk = os.statvfs(os.getcwd())
                    stats.disk_total = disk.f_frsize * disk.f_blocks
                    stats.disk_free = disk.f_frsize * disk.f_bavail
                    stats.disk_used = stats.disk_total - stats.disk_free
                    stats.disk_percent = (stats.disk_used / stats.disk_total) * 100 if stats.disk_total > 0 else 0
                else:
                    disk = psutil.disk_usage('/')
                    stats.disk_total = disk.total
                    stats.disk_used = disk.used
                    stats.disk_free = disk.free
                    stats.disk_percent = disk.percent
            except:
                try:
                    disk = os.statvfs(os.getcwd())
                    stats.disk_total = disk.f_frsize * disk.f_blocks
                    stats.disk_free = disk.f_frsize * disk.f_bavail
                    stats.disk_used = stats.disk_total - stats.disk_free
                    stats.disk_percent = (stats.disk_used / stats.disk_total) * 100 if stats.disk_total > 0 else 0
                except:
                    pass
            try:
                net_if = psutil.net_if_addrs()
                stats.network_interfaces = list(net_if.keys())
            except:
                pass
            try:
                process = psutil.Process()
                stats.process_memory = process.memory_info().rss
            except:
                pass
        except Exception as e:
            logging.debug(f"Tizim statistikasini olishda xato: {e}")

    if stats.cpu_count_logical == 0:
        stats.cpu_count_logical = cpu_count()

    # Disk fallback
    if stats.disk_total == 0:
        try:
            if PLATFORM_INFO['is_windows']:
                import ctypes
                free_bytes = ctypes.c_ulonglong(0)
                total_bytes = ctypes.c_ulonglong(0)
                ctypes.windll.kernel32.GetDiskFreeSpaceExW(
                    ctypes.c_wchar_p(os.getcwd()), None,
                    ctypes.pointer(total_bytes), ctypes.pointer(free_bytes)
                )
                stats.disk_total = total_bytes.value
                stats.disk_free = free_bytes.value
                stats.disk_used = stats.disk_total - stats.disk_free
                stats.disk_percent = (stats.disk_used / stats.disk_total) * 100 if stats.disk_total > 0 else 0
            else:
                disk = os.statvfs(os.getcwd())
                stats.disk_total = disk.f_frsize * disk.f_blocks
                stats.disk_free = disk.f_frsize * disk.f_bavail
                stats.disk_used = stats.disk_total - stats.disk_free
                stats.disk_percent = (stats.disk_used / stats.disk_total) * 100 if stats.disk_total > 0 else 0
        except:
            pass

    return stats

def format_size(size_bytes: int) -> str:
    if size_bytes == 0:
        return "0 B"
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if size_bytes < 1024.0:
            return f"{size_bytes:.2f} {unit}"
        size_bytes /= 1024.0
    return f"{size_bytes:.2f} PB"

def print_system_stats(stats: SystemStats, verbose: bool = False):
    print(colored("\n🖥️  TIZIM STATISTIKASI", 'cyan', attrs=['bold']))
    print(colored("═" * 60, 'white'))
    print(colored("📋 OPERATSION TIZIM:", 'yellow'))
    print(f"  • Nom: {stats.os_name} {stats.os_version}")
    print(f"  • Arxitektura: {stats.os_arch}")
    print(f"  • Hostname: {stats.hostname}")
    if stats.is_android:
        print(colored(f"  • Android (PyDroid3) - API Level: {stats.android_api_level}", 'green'))
    print(colored("\n🐍 PYTHON MUHITI:", 'yellow'))
    print(f"  • Versiya: {stats.python_version}")
    print(f"  • Implementatsiya: {stats.python_implementation}")
    if verbose:
        print(f"  • Kompilyator: {stats.python_compiler}")
    print(colored("\n⚡ PROTSESSOR (CPU):", 'yellow'))
    if stats.cpu_count_physical > 0:
        print(f"  • Fizik yadrolar: {stats.cpu_count_physical}")
    print(f"  • Mantiqiy yadrolar: {stats.cpu_count_logical}")
    if stats.cpu_freq_current > 0:
        print(f"  • Chastota: {stats.cpu_freq_current:.0f} MHz (Maks: {stats.cpu_freq_max:.0f} MHz)")
    if stats.cpu_percent > 0:
        print(f"  • Yuklanish: {stats.cpu_percent:.1f}%")
    print(colored("\n🧠 XOTIRA (RAM):", 'yellow'))
    if stats.ram_total > 0:
        print(f"  • Jami: {format_size(stats.ram_total)}")
        print(f"  • Band: {format_size(stats.ram_used)} ({stats.ram_percent:.1f}%)")
        print(f"  • Bo'sh: {format_size(stats.ram_available)}")
    else:
        print("  • RAM ma'lumoti mavjud emas")
    if stats.swap_total > 0:
        print(f"  • SWAP: {format_size(stats.swap_used)} / {format_size(stats.swap_total)} ({stats.swap_percent:.1f}%)")
    print(colored("\n💾 DISK:", 'yellow'))
    print(f"  • Jami: {format_size(stats.disk_total)}")
    print(f"  • Band: {format_size(stats.disk_used)} ({stats.disk_percent:.1f}%)")
    print(f"  • Bo'sh: {format_size(stats.disk_free)}")
    if verbose and stats.network_interfaces:
        print(colored("\n🌐 TARMOQ INTERFEYSLARI:", 'yellow'))
        for iface in stats.network_interfaces[:5]:
            print(f"  • {iface}")
    print(colored("\n📊 DASTUR JARAYONI:", 'yellow'))
    print(f"  • PID: {stats.process_pid}")
    if stats.process_memory > 0:
        print(f"  • Xotira: {format_size(stats.process_memory)}")
    print(colored("═" * 60, 'white'))

# ═══════════════════════════════════════════════════════════════════════════════
# KENGAYTMALAR
# ═══════════════════════════════════════════════════════════════════════════════

SUPPORTED_EXTENSIONS = {
    # Web
    'html', 'htm', 'xhtml', 'dhtml', 'shtml', 'css', 'scss', 'sass', 'less', 'styl', 'stylus',
    'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'coffee', 'litcoffee',
    'vue', 'svelte', 'astro', 'solid', 'qwik',
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps',
    'asp', 'aspx', 'ascx', 'asmx', 'ashx', 'axd', 'asax',
    'jsp', 'jspf', 'jspx', 'do', 'action', 'tag', 'tld',
    'ejs', 'pug', 'jade', 'haml', 'slim', 'twig', 'blade', 'mustache', 'handlebars', 'hbs',
    'liquid', 'njk', 'nunjucks',
    # Python
    'py', 'pyw', 'pyi', 'pyx', 'pxd', 'pxi', 'ipynb', 'rpy', 'cpy', 'gyp', 'gypi',
    # Java
    'java', 'jav', 'groovy', 'gvy', 'gy', 'gsh', 'gradle',
    'kt', 'kts', 'ktm', 'scala', 'sc', 'sbt', 'clj', 'cljs', 'cljc', 'edn',
    # C/C++
    'c', 'h', 'cc', 'cpp', 'cxx', 'c++', 'hh', 'hpp', 'hxx', 'h++', 'm', 'mm',
    'cs', 'csx', 'csproj',
    # .NET
    'vb', 'vbs', 'vba', 'bas', 'frm', 'cls', 'fs', 'fsi', 'fsx', 'fsscript',
    'ps1', 'psm1', 'psd1', 'psc1',
    # Ruby
    'rb', 'rbw', 'rake', 'gemspec', 'rbx', 'rhtml', 'ru',
    # Go/Rust
    'go', 'mod', 'sum', 'tmpl', 'rs', 'rlib',
    # Swift/Dart
    'swift', 'storyboard', 'xib', 'plist', 'dart',
    # Functional
    'hs', 'lhs', 'erl', 'hrl', 'ex', 'exs', 'eex', 'ml', 'mli', 'lisp', 'cl', 'rkt', 'scm',
    # Shell
    'sh', 'bash', 'zsh', 'fish', 'bat', 'cmd', 'pl', 'pm', 'lua', 'tcl', 'r', 'R', 'Rmd', 'jl',
    # DB
    'sql', 'mysql', 'psql', 'plsql', 'tsql', 'sqlite', 'prisma',
    # Config
    'json', 'json5', 'jsonc', 'xml', 'xsd', 'xsl', 'xslt', 'svg',
    'yml', 'yaml', 'ini', 'cfg', 'conf', 'config', 'properties', 'prop',
    'env', 'envrc', 'toml', 'htaccess', 'editorconfig', 'gitignore', 'dockerignore',
    # Markup
    'md', 'markdown', 'rst', 'adoc', 'asciidoc', 'tex', 'sty', 'bib', 'latex', 'org', 'wiki',
    # Data
    'csv', 'tsv', 'proto', 'graphql', 'gql',
    # Build
    'make', 'mk', 'cmake', 'dockerfile', 'vagrantfile', 'tf', 'tfvars', 'hcl',
    # Text
    'txt', 'text', 'log',
    # Game
    'gd', 'shader', 'hlsl', 'glsl',
    # Assembly
    'asm', 's', 'nasm',
    # Fortran/Cobol/Pascal
    'f', 'for', 'f90', 'f95', 'f03', 'f08', 'cob', 'cbl', 'cobol', 'pas', 'pp', 'dpr',
    # Ada/Verilog
    'adb', 'ads', 'ada', 'v', 'vh', 'vhd', 'vhdl', 'sv',
    # Other
    'nim', 'cr', 'zig', 'hx', 'd', 'sol', 'vy', 'lock',
    'desktop', 'service', 'xaml', 'qml', 'vala',
    'pem', 'crt', 'key', 'pub',
}

BINARY_AND_MEDIA_EXTENSIONS = {
    # Rasmlar
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svgz',
    'ico', 'icns', 'heic', 'heif', 'avif', 'psd', 'ai', 'eps',
    'raw', 'cr2', 'nef', 'arw', 'dng',
    # Audio
    'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'opus', 'wma', 'mid', 'midi',
    # Video
    'mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg',
    # Arxivlar
    'zip', 'tar', 'gz', 'bz2', 'xz', '7z', 'rar', 'zst',
    # Binary
    'exe', 'dll', 'so', 'dylib', 'a', 'lib', 'obj', 'o', 'ko', 'sys',
    'bin', 'elf', 'macho', 'msi', 'app', 'deb', 'rpm', 'apk', 'ipa',
    # DB
    'db', 'sqlite', 'sqlite3', 'mdb', 'accdb',
    # Fontlar
    'ttf', 'otf', 'woff', 'woff2', 'eot',
    # Boshqa
    'pyc', 'pyo', 'pyd', 'class', 'wasm', 'pdb', 'cache',
    # 3D
    'blend', 'max', '3ds', 'fbx', 'dae', 'gltf', 'glb', 'stl',
    # Disk
    'iso', 'img', 'dmg', 'vhd', 'vhdx', 'vmdk',
    'maker_tz.md',
}

SKIP_FOLDERS = {
    # Virtualenv
    'venv', 'env', '.env', '.venv', 'virtualenv',
    # Node
    'node_modules', 'bower_components',
    # VCS
    '.git', '.svn', '.hg', '.github',
    # Python
    '__pycache__', '.pytest_cache', '.mypy_cache', '.tox', '.nox',
    'htmlcov', '.coverage', 'coverage', '.cache', '.eggs',
    'build', 'dist', 'develop-eggs', 'eggs',
    # venv dirs
    'Lib', 'Scripts', 'Include', 'bin', 'lib', 'lib64', 'include',
    'site-packages', 'dist-packages',
    # IDE
    '.idea', '.vscode', '.vs', '.eclipse', '.settings', '.android', '.gradle',
    # Build
    'target', 'out', 'output', 'obj', 'Debug', 'Release',
    '.next', '.nuxt', 'tmp', 'temp', '.tmp', 'cache', 'logs',
    # Docker/Pkg
    '.docker', 'vendor', 'packages',
    # Windows tizim
    'Windows', 'Program Files', 'Program Files (x86)', 'ProgramData',
    'System32', 'SysWOW64', 'WinSxS', 'Microsoft.NET',
    'Recovery', 'System Volume Information', '$Recycle.Bin',
    'Config.Msi', 'MSOCache', 'PerfLogs',
    # Linux tizim
    'proc', 'sys', 'dev', 'run', 'boot', 'etc', 'var', 'usr', 'sbin',
    'lost+found', 'mnt', 'media', 'srv', 'opt',
    # Mac tizim
    'System', 'Library', 'Applications',
}

SKIP_FILE_PATTERNS = [
    '*.pyc', '*.pyo', '*.pyd', '*.class', '*.jar', '*.war', '*.ear',
    '*.exe', '*.dll', '*.so', '*.dylib',
    '*.min.js', '*.min.css', '*.bundle.js', '*.chunk.js',
    '*.map', '*.js.map', '*.css.map',
    '*.lock', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml',
    '*.log', '*.bak', '*.backup', '*.old',
    '*.tmp', '*.temp', '*.swp', '*.swo', '*~',
    '.DS_Store', 'Thumbs.db', 'desktop.ini',
    '*.zip', '*.tar', '*.gz', '*.bz2', '*.7z', '*.rar',
    '*.pdf', '*.doc', '*.docx', '*.xls', '*.xlsx', '*.ppt', '*.pptx',
    'CodeAnalyzer*.py', 'respons_*.*',
]

# ═══════════════════════════════════════════════════════════════════════════════
# MA'LUMOT KLASSLARI
# ═══════════════════════════════════════════════════════════════════════════════

@dataclass
class FileInfo:
    path: str
    name: str
    extension: str
    size: int
    lines: int = 0
    content: str = ""
    hash: str = ""
    encoding: str = "utf-8"
    error: Optional[str] = None

@dataclass
class AnalysisStats:
    total_files: int = 0
    total_lines: int = 0
    total_size: int = 0
    processed_files: int = 0
    skipped_files: int = 0
    error_files: int = 0
    file_types: Dict[str, Dict[str, int]] = field(default_factory=dict)
    largest_files: List[Tuple[str, int, int]] = field(default_factory=list)
    encoding_stats: Dict[str, int] = field(default_factory=dict)
    start_time: Optional[datetime] = None
    end_time: Optional[datetime] = None

    def duration_seconds(self) -> float:
        if self.start_time and self.end_time:
            return (self.end_time - self.start_time).total_seconds()
        return 0.0

# ═══════════════════════════════════════════════════════════════════════════════
# SAMPLE TREE (Project Generator namunasi)
# ═══════════════════════════════════════════════════════════════════════════════

SAMPLE_TREE = """construction_factory_bot/
├── 📁 alembic/                    # Database migratsiyalari
│   ├── versions/                  # Migratsiya fayllari
│   └── env.py                     # Migratsiya muhiti
├── 📁 backups/                    # Backup fayllari
├── 📁 database/                   # Database modullari
│   ├── __init__.py
│   ├── models.py                  # SQLAlchemy modellari
│   ├── crud.py                    # CRUD operatsiyalari
│   ├── session.py                 # Database sessiyasi
│   └── alembic_versions.py        # Migratsiya versiyalari
├── 📁 handlers/                   # Bot handlerlari
│   ├── __init__.py
│   ├── start.py                   # Start handler
│   ├── warehouse.py               # Ombor handler
│   ├── production.py              # Ishlab chiqarish handler
│   ├── reports.py                 # Hisobotlar handler
│   ├── admin.py                   # Admin paneli
│   ├── employees.py               # Xodimlar handler
│   ├── notifications.py           # Bildirishnomalar handler
│   └── sales.py                   # Sotuvlar handler
├── 📁 keyboards/                  # Klaviatura modullari
│   ├── __init__.py
│   ├── main_menu.py               # Asosiy menyu
│   ├── admin_menu.py              # Admin menyusi
│   └── inline_keyboards.py        # Inline tugmalar
├── 📁 logs/                       # Log fayllari
├── 📁 reports/                    # Hisobotlar
│   ├── excel/                     # Excel hisobotlar
│   └── charts/                    # Grafiklar
├── 📁 static/                     # Statik fayllar
│   └── images/                    # Rasmlar
├── 📁 utils/                      # Yordamchi funksiyalar
│   ├── __init__.py
│   ├── formulas.py                # Mahsulot formulalari
│   ├── calculations.py            # Hisob-kitoblar
│   ├── excel_reports.py           # Excel hisobotlar
│   ├── charts.py                  # Grafik yaratish
│   ├── notifications.py           # Push bildirishnomalar
│   └── helpers.py                 # Yordamchi funksiyalar
├── .env                           # Konfiguratsiya (shaxsiy)
├── .env.example                   # Konfiguratsiya namunasi
├── .gitignore                     # Git ignore
├── alembic.ini                    # Alembic konfiguratsiyasi
├── config.py                      # Asosiy konfiguratsiya
├── main.py                        # Asosiy fayl
├── README.md                      # Loyiha haqida ma'lumot
└── requirements.txt               # Kutubxonalar ro'yxati
"""

# ═══════════════════════════════════════════════════════════════════════════════
# UNIVERSAL PROJECT GENERATOR
# ═══════════════════════════════════════════════════════════════════════════════

def parse_tree(tree_str: str) -> dict:
    """TREE matn formatini dict ga aylantirish."""
    structure = {}
    stack = []
    for line in tree_str.splitlines():
        line = line.rstrip()
        if not line.strip():
            continue
        if "#" in line:
            line_part, comment = line.split("#", 1)
            comment = comment.strip()
        else:
            line_part = line
            comment = ""
        name = (line_part.replace("├──", "").replace("└──", "")
                          .replace("│", "").replace("📁", "").strip())
        if not name:
            continue
        level = line.count("│")
        while len(stack) > level:
            stack.pop()
        if name.endswith("/"):
            folder_name = name.rstrip("/")
            d = structure
            for p in stack:
                d = d.setdefault(p, {})
            d[folder_name] = d.get(folder_name, {})
            if comment:
                d[folder_name]["_comment"] = comment
            stack.append(folder_name)
        else:
            d = structure
            for p in stack:
                d = d.setdefault(p, {})
            d[name] = ""
            if comment:
                d["_comment"] = comment
    return structure

def dict_to_tree(structure: dict, prefix: str = "") -> str:
    """Dict strukturasini TREE ko'rinishiga aylantirish."""
    lines = []
    items = [(k, v) for k, v in structure.items() if k != "_comment"]
    for i, (name, content) in enumerate(items):
        connector = "└── " if i == len(items) - 1 else "├── "
        comment = ""
        if isinstance(content, str) and content:
            comment = f" # {content}"
        elif isinstance(content, dict) and "_comment" in content:
            comment = f" # {content['_comment']}"
        lines.append(f"{prefix}{connector}{name}{comment}")
        if isinstance(content, dict):
            extension = "    " if i == len(items) - 1 else "│   "
            lines.append(dict_to_tree(content, prefix + extension))
    return "\n".join(lines)

def create_structure(root: str, structure: dict) -> List[str]:
    """Dict strukturasidan haqiqiy papka/fayl strukturasini yaratish."""
    root_path = Path(root)
    root_path.mkdir(parents=True, exist_ok=True)
    created = []

    def recurse(path: Path, sub: dict):
        for name, content in sub.items():
            if name == "_comment":
                continue
            full_path = path / name
            if isinstance(content, dict):
                full_path.mkdir(parents=True, exist_ok=True)
                created.append(f"📁 {full_path.relative_to(root_path)}/")
                recurse(full_path, content)
            else:
                full_path.parent.mkdir(parents=True, exist_ok=True)
                with open(full_path, "w", encoding="utf-8") as f:
                    comment = sub.get("_comment", "")
                    if comment:
                        f.write(f"# {comment}\n")
                    if content:
                        f.write(content)
                created.append(f"📄 {full_path.relative_to(root_path)}")

    recurse(root_path, structure)
    return created

def write_sample_file(fmt: str, output_dir: str = ".") -> str:
    """Namuna faylni yozish (JSON / YAML / TREE)."""
    structure = parse_tree(SAMPLE_TREE)
    file_name = ""
    if fmt == "1":
        file_name = os.path.join(output_dir, "sample.json")
        with open(file_name, "w", encoding="utf-8") as f:
            json.dump(structure, f, indent=4, ensure_ascii=False, sort_keys=False)
    elif fmt == "2":
        if not HAS_YAML:
            print("[X] PyYAML o'rnatilmagan. pip install pyyaml")
            return ""
        file_name = os.path.join(output_dir, "sample.yaml")
        with open(file_name, "w", encoding="utf-8") as f:
            yaml.dump(structure, f, sort_keys=False, allow_unicode=True)
    elif fmt == "3":
        file_name = os.path.join(output_dir, "sample.txt")
        with open(file_name, "w", encoding="utf-8") as f:
            f.write(SAMPLE_TREE)
    else:
        raise ValueError("Noto'g'ri tanlov!")
    return file_name

def load_structure_from_file(file_path: str, fmt: str) -> dict:
    """Fayldan struktura yuklash (JSON/YAML/TREE)."""
    if fmt == "1":
        with open(file_path, "r", encoding="utf-8") as f:
            return json.load(f)
    elif fmt == "2":
        if not HAS_YAML:
            raise RuntimeError("PyYAML o'rnatilmagan")
        with open(file_path, "r", encoding="utf-8") as f:
            return yaml.safe_load(f)
    elif fmt == "3":
        with open(file_path, "r", encoding="utf-8") as f:
            return parse_tree(f.read())
    else:
        raise ValueError("Noto'g'ri format!")

# ═══════════════════════════════════════════════════════════════════════════════
# TREE GENERATOR (papkadan daraxt)
# ═══════════════════════════════════════════════════════════════════════════════

def generate_tree_from_folder(root_path: str, max_depth: int = 5,
                              ignore_patterns: List[str] = None,
                              use_unicode: bool = True) -> str:
    """Papka strukturasini daraxt ko'rinishida yaratish."""
    if ignore_patterns is None:
        ignore_patterns = list(SKIP_FOLDERS)

    root_path = os.path.abspath(root_path)
    if not os.path.isdir(root_path):
        raise ValueError(f"Papka mavjud emas: {root_path}")

    if use_unicode:
        TEE, ELBOW, PIPE, SPACE = '├── ', '└── ', '│   ', '    '
    else:
        TEE, ELBOW, PIPE, SPACE = '|-- ', '\\-- ', '|   ', '    '

    lines = []

    def _should_ignore(name):
        return any(fnmatch.fnmatch(name, pat) for pat in ignore_patterns)

    def _walk(current_path: str, prefix: str, depth: int):
        if depth > max_depth:
            lines.append(f"{prefix}...")
            return
        try:
            entries = sorted(os.listdir(current_path))
        except PermissionError:
            lines.append(f"{prefix}[Ruxsat yo'q]")
            return
        entries = [e for e in entries if not _should_ignore(e)]
        dirs = [e for e in entries if os.path.isdir(os.path.join(current_path, e))]
        files = [e for e in entries if os.path.isfile(os.path.join(current_path, e))]
        ordered = dirs + files
        for idx, entry in enumerate(ordered):
            is_last = (idx == len(ordered) - 1)
            connector = ELBOW if is_last else TEE
            full = os.path.join(current_path, entry)
            if os.path.isdir(full):
                lines.append(f"{prefix}{connector}📁 {entry}/")
                extension = SPACE if is_last else PIPE
                _walk(full, prefix + extension, depth + 1)
            else:
                try:
                    size_str = format_size(os.path.getsize(full))
                except:
                    size_str = "?"
                lines.append(f"{prefix}{connector}📄 {entry} ({size_str})")

    root_name = os.path.basename(root_path) or root_path
    lines.append(f"📁 {root_name}/")
    _walk(root_path, "", 1)
    return "\n".join(lines)

# ═══════════════════════════════════════════════════════════════════════════════
# DEMO GENERATOR
# ═══════════════════════════════════════════════════════════════════════════════

def generate_demo_files(output_dir: str, demo_type: str = 'all') -> List[str]:
    os.makedirs(output_dir, exist_ok=True)
    created = []
    demos = {
        'python': {
            'demo_python.py': '''#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Demo Python skripti."""
import sys
from datetime import datetime


def fibonacci(n: int) -> list:
    result = [0, 1]
    for _ in range(n - 2):
        result.append(result[-1] + result[-2])
    return result[:n]


def main():
    print(f"Python: {sys.version}")
    print(f"Vaqt: {datetime.now().isoformat()}")
    print(f"Fib(10): {fibonacci(10)}")


if __name__ == "__main__":
    main()
''',
            'demo_data.json': '{\n    "name": "Demo",\n    "version": "1.0",\n    "active": true\n}\n',
            'demo_config.yml': 'app:\n  name: Demo\n  debug: false\n',
        },
        'web': {
            'demo_index.html': '<!DOCTYPE html>\n<html><head><meta charset="UTF-8"><title>Demo</title></head><body><h1>Demo</h1></body></html>\n',
            'demo_style.css': 'body { font-family: sans-serif; background: #667eea; color: white; }\n',
            'demo_script.js': 'console.log("Demo loaded");\n',
        },
        'data': {
            'demo_data.csv': 'id,name,value\n1,Alpha,100\n2,Beta,200\n',
            'demo_data.txt': 'Namuna matn fayli.\n',
            'demo_sql.sql': 'CREATE TABLE demo (id INTEGER PRIMARY KEY, name TEXT);\n',
        },
    }
    selected = demos if demo_type == 'all' else {demo_type: demos.get(demo_type, {})}
    for category, files in selected.items():
        for filename, content in files.items():
            full_path = os.path.join(output_dir, filename)
            with open(full_path, 'w', encoding='utf-8') as f:
                f.write(content)
            created.append(f"📄 {filename}")
    return created

# ═══════════════════════════════════════════════════════════════════════════════
# KOD TAHLILI
# ═══════════════════════════════════════════════════════════════════════════════

def should_skip_path(path: str, custom_skip_patterns: List[str] = None) -> Tuple[bool, str]:
    path_obj = Path(path)
    for part in path_obj.parts:
        if part in SKIP_FOLDERS:
            return True, f"Skip papka: {part}"
    if os.path.isfile(path):
        filename = path_obj.name
        extension = path_obj.suffix.lower()
        if extension and extension[1:] in BINARY_AND_MEDIA_EXTENSIONS:
            return True, f"Binary/media: {extension}"
        all_patterns = SKIP_FILE_PATTERNS.copy()
        if custom_skip_patterns:
            all_patterns.extend(custom_skip_patterns)
        for pattern in all_patterns:
            if fnmatch.fnmatch(filename, pattern):
                return True, f"Skip pattern: {pattern}"
    return False, ""

def detect_encoding(filepath: str) -> str:
    try:
        with open(filepath, 'rb') as f:
            raw_data = f.read(100000)
            result = chardet.detect(raw_data)
            return result['encoding'] if result['encoding'] else 'utf-8'
    except:
        return 'utf-8'

def read_file_safely(filepath: str, max_size_mb: int = 50) -> FileInfo:
    file_info = FileInfo(
        path=filepath,
        name=os.path.basename(filepath),
        extension=os.path.splitext(filepath)[1].lower(),
        size=os.path.getsize(filepath)
    )
    try:
        size_mb = file_info.size / (1024 * 1024)
        if size_mb > max_size_mb:
            file_info.error = f"Hajm limiti: {size_mb:.1f}MB > {max_size_mb}MB"
            return file_info
        file_info.encoding = detect_encoding(filepath)
        with open(filepath, 'r', encoding=file_info.encoding, errors='ignore') as f:
            file_info.content = f.read()
        file_info.lines = file_info.content.count('\n') + 1
        file_info.hash = hashlib.sha256(file_info.content.encode('utf-8')).hexdigest()[:16]
    except Exception as e:
        file_info.error = f"O'qish xatosi: {str(e)}"
    return file_info

def print_progress(current: int, total: int, bar_length: int = 40) -> None:
    if total == 0:
        return
    percent = current / total
    filled = int(round(percent * bar_length))
    bar = '█' * filled + '░' * (bar_length - filled)
    sys.stdout.write(f"\r[{colored(bar, 'green')}] {current}/{total} ({percent:.1%})")
    sys.stdout.flush()

def analyze_folder(root_folder: str, max_workers: int = None,
                   max_size_mb: int = 50, custom_skip_patterns: List[str] = None,
                   verbose: bool = False) -> Tuple[Dict, AnalysisStats, Set[str]]:
    if max_workers is None:
        max_workers = 2 if PLATFORM_INFO['is_android'] else min(cpu_count(), 8)
    stats = AnalysisStats()
    stats.start_time = datetime.now()
    structure = {}
    existing_extensions = set()
    print(colored("\n📂 Fayllar skanerlanmoqda...", 'cyan'))
    file_list = []
    skipped_reasons = defaultdict(int)

    is_system_root = root_folder in ['C:\\', 'C:', '/', '/storage/emulated/0']
    if is_system_root:
        print(colored("⚠️  Asosiy disk skanerlanmoqda - bu ko'p vaqt olishi mumkin!", 'yellow'))

    for root, dirs, files in os.walk(root_folder):
        dirs[:] = [d for d in dirs if not should_skip_path(os.path.join(root, d))[0]]
        for file in files:
            full_path = os.path.join(root, file)
            skip, reason = should_skip_path(full_path, custom_skip_patterns)
            if skip:
                stats.skipped_files += 1
                skipped_reasons[reason] += 1
                continue
            ext = os.path.splitext(file)[1].lower()
            if ext and ext[1:] in SUPPORTED_EXTENSIONS:
                file_list.append(full_path)
                existing_extensions.add(ext[1:])
            elif not ext:
                if file.upper() in ['DOCKERFILE', 'MAKEFILE', 'JENKINSFILE', 'VAGRANTFILE']:
                    file_list.append(full_path)
                    existing_extensions.add(file.lower())

    stats.total_files = len(file_list)
    if stats.total_files == 0:
        print(colored("\n⚠️  Tahlil qilinadigan fayllar topilmadi!", 'yellow'))
        return structure, stats, existing_extensions

    print(colored(f"\n📊 {stats.total_files} ta fayl topildi", 'green'))
    if stats.skipped_files > 0:
        print(colored(f"⏭️  {stats.skipped_files} ta fayl skip qilindi", 'yellow'))
        if verbose:
            for reason, count in skipped_reasons.items():
                print(colored(f"   - {reason}: {count} ta", 'white'))
    print(colored(f"\n🚀 Tahlil boshlandi ({max_workers} ta ishchi)...", 'cyan'))

    processed = 0
    results = {}
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_file = {executor.submit(read_file_safely, fp, max_size_mb): fp for fp in file_list}
        for future in as_completed(future_to_file):
            filepath = future_to_file[future]
            processed += 1
            try:
                file_info = future.result()
                if file_info.error:
                    stats.error_files += 1
                    results[filepath] = f"// ERROR: {file_info.error}"
                else:
                    stats.processed_files += 1
                    stats.total_lines += file_info.lines
                    stats.total_size += file_info.size
                    ext = file_info.extension[1:] if file_info.extension else 'no_ext'
                    if ext not in stats.file_types:
                        stats.file_types[ext] = {'count': 0, 'lines': 0, 'size': 0}
                    stats.file_types[ext]['count'] += 1
                    stats.file_types[ext]['lines'] += file_info.lines
                    stats.file_types[ext]['size'] += file_info.size
                    stats.encoding_stats[file_info.encoding] = stats.encoding_stats.get(file_info.encoding, 0) + 1
                    stats.largest_files.append((filepath, file_info.size, file_info.lines))
                    stats.largest_files.sort(key=lambda x: x[1], reverse=True)
                    stats.largest_files = stats.largest_files[:20]
                    results[filepath] = file_info.content
                print_progress(processed, stats.total_files)
            except Exception as e:
                stats.error_files += 1
                results[filepath] = f"// ERROR: {str(e)}"

    print()
    print(colored("\n📁 JSON struktura qurilmoqda...", 'cyan'))
    for filepath, content in results.items():
        rel_path = os.path.relpath(filepath, root_folder)
        folder = os.path.dirname(rel_path)
        filename = os.path.basename(filepath)
        if folder not in structure:
            structure[folder] = {}
        structure[folder][filename] = content

    stats.end_time = datetime.now()
    return structure, stats, existing_extensions

def write_output_files_ordered(structure: Dict, output_folder: str) -> List[str]:
    """TXT fayllar — asl tartibda saqlash."""
    saved_files = []
    categorized = defaultdict(lambda: defaultdict(dict))
    for folder, files in structure.items():
        for filename, content in files.items():
            ext = os.path.splitext(filename)[1].lower()
            ext = ext[1:] if ext else filename.lower()
            categorized[ext][folder][filename] = content

    for ext, data in categorized.items():
        if data:
            output_file = os.path.join(output_folder, f'respons_{ext}.{ext}')
            try:
                with open(output_file, 'w', encoding='utf-8') as f:
                    f.write(f"{'='*80}\n")
                    f.write("CODE ANALYZER PRO - ULTIMATE EDITION v3\n")
                    f.write(f"Extension: .{ext}\n")
                    f.write(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
                    f.write(f"{'='*80}\n\n")
                    for folder, files in data.items():
                        for filename, content in files.items():
                            rel_path = os.path.join(folder, filename) if folder else filename
                            f.write(f"\n{'─'*80}\n📄 FILE: {rel_path}\n{'─'*80}\n\n")
                            f.write(content)
                            f.write("\n\n")
                saved_files.append(output_file)
            except Exception as e:
                logging.error(f"Fayl yozishda xato {output_file}: {e}")

    all_files_output = os.path.join(output_folder, 'respons_ALL_FILES.txt')
    try:
        with open(all_files_output, 'w', encoding='utf-8') as f:
            f.write(f"{'═'*80}\n")
            f.write("CODE ANALYZER PRO - ULTIMATE EDITION v3\n")
            f.write("ALL FILES - COMPLETE PROJECT ANALYSIS\n")
            f.write(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"Platform: {PLATFORM_INFO['system']}\n")
            f.write(f"{'═'*80}\n\n")
            for folder, files in structure.items():
                f.write(f"\n{'█'*80}\n📁 FOLDER: {folder if folder else '(ROOT)'}\n{'█'*80}\n\n")
                for filename, content in files.items():
                    f.write(f"\n{'─'*80}\n📄 FILE: {filename}\n{'─'*80}\n\n")
                    f.write(content)
                    f.write("\n\n")
        saved_files.append(all_files_output)
    except Exception as e:
        logging.error(f"Barcha fayllarni yozishda xato: {e}")

    return saved_files

def generate_html_report(stats: AnalysisStats, git_info: Dict, output_folder: str,
                         structure: Dict, system_stats: SystemStats) -> str:
    report_file = os.path.join(output_folder, 'analysis_report.html')
    duration = stats.duration_seconds()
    html = f'''<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Analyzer Pro v3 - Analysis Report</title>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{ font-family: 'Segoe UI', 'Roboto', system-ui, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0; padding: 20px; min-height: 100vh; }}
        .container {{ max-width: 1400px; margin: 0 auto; }}
        .header {{ background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 30px; border-radius: 20px; margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); }}
        .header h1 {{ font-size: 2.5em; margin-bottom: 10px; }}
        .stats-grid {{ display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 30px; }}
        .stat-card {{ background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 15px; padding: 25px; text-align: center; }}
        .stat-card .number {{ font-size: 3em; font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; }}
        .section {{ background: rgba(255,255,255,0.05); border-radius: 15px;
            padding: 25px; margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1); }}
        .section h2 {{ color: #667eea; margin-bottom: 20px; font-size: 1.8em;
            border-bottom: 2px solid rgba(102,126,234,0.3); padding-bottom: 10px; }}
        table {{ width: 100%; border-collapse: collapse; }}
        th, td {{ padding: 12px 15px; text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1); }}
        th {{ background: rgba(102,126,234,0.2); color: #fff; }}
        tr:hover {{ background: rgba(255,255,255,0.05); }}
        .footer {{ text-align: center; padding: 20px; color: #666; }}
        .success {{ color: #51cf66; }}
        .error {{ color: #ff6b6b; }}
        .warning {{ color: #ffd43b; }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Code Analysis Report</h1>
            <p>Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</p>
            <p>Duration: {duration:.2f} seconds | Platform: {system_stats.os_name}</p>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="number">{stats.processed_files:,}</div><div class="label">Processed Files</div></div>
            <div class="stat-card"><div class="number">{stats.total_lines:,}</div><div class="label">Total Lines</div></div>
            <div class="stat-card"><div class="number">{stats.total_size / (1024*1024):.2f}</div><div class="label">Total Size (MB)</div></div>
            <div class="stat-card"><div class="number">{len(stats.file_types)}</div><div class="label">File Types</div></div>
        </div>
        <div class="section">
            <h2>🖥️ System Information</h2>
            <table>
                <tr><td>OS:</td><td>{system_stats.os_name} {system_stats.os_version}</td></tr>
                <tr><td>Architecture:</td><td>{system_stats.os_arch}</td></tr>
                <tr><td>CPU Cores:</td><td>{system_stats.cpu_count_logical} logical</td></tr>
                <tr><td>RAM Total:</td><td>{format_size(system_stats.ram_total)}</td></tr>
                <tr><td>Disk Total:</td><td>{format_size(system_stats.disk_total)}</td></tr>
                <tr><td>Disk Free:</td><td>{format_size(system_stats.disk_free)}</td></tr>
                <tr><td>Python:</td><td>{system_stats.python_version}</td></tr>
            </table>
        </div>
        <div class="section">
            <h2>📈 Analysis Statistics</h2>
            <table>
                <tr><td>Total Files Found:</td><td>{stats.total_files:,}</td></tr>
                <tr><td>Successfully Processed:</td><td class="success">{stats.processed_files:,}</td></tr>
                <tr><td>Skipped Files:</td><td class="warning">{stats.skipped_files:,}</td></tr>
                <tr><td>Files with Errors:</td><td class="error">{stats.error_files:,}</td></tr>
            </table>
        </div>
        <div class="section">
            <h2>📁 File Types Distribution</h2>
            <table>
                <tr><th>Extension</th><th>Count</th><th>Lines</th><th>Size (KB)</th></tr>
    '''
    for ext, data in sorted(stats.file_types.items(), key=lambda x: x[1]['count'], reverse=True)[:20]:
        html += f'''
                <tr>
                    <td>.{ext}</td>
                    <td>{data['count']}</td>
                    <td>{data['lines']:,}</td>
                    <td>{data['size'] / 1024:.2f}</td>
                </tr>
        '''
    html += '''
            </table>
        </div>
        <div class="footer">
            <p>Code Analyzer Pro Ultimate Edition v3 | Cross-Platform</p>
            <p>Created by Otaboyev Sardorbek | 📧 prodevuzoff@gmail.com</p>
        </div>
    </div>
</body>
</html>
'''
    with open(report_file, 'w', encoding='utf-8') as f:
        f.write(html)
    return report_file

def get_git_info(root_folder: str) -> Dict[str, Any]:
    git_info = {'is_git_repo': False, 'branch': None, 'last_commit': None,
                'remote_url': None, 'total_commits': None}
    if PLATFORM_INFO['is_android']:
        return git_info
    try:
        result = subprocess.run(['git', 'rev-parse', '--is-inside-work-tree'],
                                cwd=root_folder, capture_output=True, text=True, timeout=5)
        if result.returncode == 0:
            git_info['is_git_repo'] = True
            result = subprocess.run(['git', 'branch', '--show-current'],
                                    cwd=root_folder, capture_output=True, text=True, timeout=5)
            git_info['branch'] = result.stdout.strip()
            result = subprocess.run(['git', 'log', '-1', '--format=%H|%s|%an|%ad'],
                                    cwd=root_folder, capture_output=True, text=True, timeout=5)
            if result.stdout.strip():
                parts = result.stdout.strip().split('|')
                git_info['last_commit'] = {
                    'hash': parts[0][:8] if len(parts) > 0 else '',
                    'message': parts[1] if len(parts) > 1 else '',
                    'author': parts[2] if len(parts) > 2 else '',
                    'date': parts[3] if len(parts) > 3 else ''
                }
            result = subprocess.run(['git', 'remote', 'get-url', 'origin'],
                                    cwd=root_folder, capture_output=True, text=True, timeout=5)
            git_info['remote_url'] = result.stdout.strip()
            result = subprocess.run(['git', 'rev-list', '--count', 'HEAD'],
                                    cwd=root_folder, capture_output=True, text=True, timeout=10)
            if result.stdout.strip():
                git_info['total_commits'] = int(result.stdout.strip())
    except:
        pass
    return git_info

# ═══════════════════════════════════════════════════════════════════════════════
# MENYU TIZIMI
# ═══════════════════════════════════════════════════════════════════════════════

def get_system_root() -> str:
    if PLATFORM_INFO['is_windows']:
        return "C:\\"
    elif PLATFORM_INFO['is_android']:
        return "/storage/emulated/0"
    else:
        return "/"

def get_current_directory() -> str:
    return os.getcwd()

def show_main_menu() -> Optional[str]:
    print(colored("\n" + "═" * 60, 'yellow'))
    print(colored("🎯 CODE ANALYZER PRO v3 - ASOSIY MENYU", 'cyan', attrs=['bold']))
    print(colored("═" * 60, 'yellow'))
    print()
    print(colored("  1️⃣  📊  Kod tahlili (Code Analysis)", 'white'))
    print(colored("      Loyihani to'liq tahlil qilish va hisobot yaratish", 'blue'))
    print()
    print(colored("  2️⃣  🌳  Tree Generator", 'white'))
    print(colored("      Papka strukturasini vizual daraxt ko'rinishida chiqarish", 'blue'))
    print()
    print(colored("  3️⃣  🏗️  Project Generator (Universal)", 'white'))
    print(colored("      TREE / JSON / YAML dan loyiha strukturasini yaratish", 'blue'))
    print()
    print(colored("  4️⃣  🎯  Demo Generator", 'white'))
    print(colored("      Namuna / demo fayllar yaratish", 'blue'))
    print()
    print(colored("  5️⃣  📋  Tizim statistikasi", 'white'))
    print(colored("      Kompyuter va tizim haqida ma'lumot", 'blue'))
    print()
    print(colored("  0️⃣  🚪  Chiqish", 'red'))
    print()
    print(colored("═" * 60, 'yellow'))

    while True:
        try:
            choice = input(colored("\n➡️  Tanlang (1-5/0): ", 'green')).strip()
            if choice == '0':
                return None
            if choice in ['1', '2', '3', '4', '5']:
                return choice
            print(colored("❌ Noto'g'ri tanlov! 1-5 yoki 0 kiriting.", 'red'))
        except KeyboardInterrupt:
            print(colored("\n\n⚠️  Bekor qilindi!", 'yellow'))
            return None

def show_path_menu(title: str = "PAPKA TANLASH") -> Optional[str]:
    print(colored("\n" + "═" * 60, 'yellow'))
    print(colored(f"📁 {title}", 'cyan', attrs=['bold']))
    print(colored("═" * 60, 'yellow'))
    print()
    print(colored("  1️⃣  Tizimning asosiy diski", 'white'))
    system_root = get_system_root()
    print(colored(f"      📍 {system_root}", 'blue'))
    print()
    print(colored("  2️⃣  Joriy papka", 'white'))
    print(colored(f"      📍 {get_current_directory()}", 'blue'))
    print()
    print(colored("  3️⃣  Boshqa yo'l kiritish", 'white'))
    print()
    print(colored("  0️⃣  Orqaga", 'red'))
    print()
    print(colored("═" * 60, 'yellow'))

    while True:
        try:
            choice = input(colored("\n➡️  Tanlang (1/2/3/0): ", 'green')).strip()
            if choice == '0':
                return None
            if choice == '1':
                root_path = get_system_root()
                print(colored(f"\n⚠️  {root_path} — bu juda ko'p vaqt olishi mumkin!", 'yellow'))
                confirm = input(colored("Davom etish? (ha/yo'q): ", 'green')).strip().lower()
                if confirm in ['ha', 'h', 'yes', 'y', '1']:
                    return root_path
                continue
            elif choice == '2':
                return get_current_directory()
            elif choice == '3':
                custom_path = input(colored("\n📁 Yo'lni kiriting: ", 'green')).strip()
                if custom_path and os.path.isdir(custom_path):
                    return custom_path
                elif custom_path:
                    print(colored(f"❌ '{custom_path}' papkasi mavjud emas!", 'red'))
                    continue
                else:
                    print(colored("❌ Yo'l kiritilmadi!", 'red'))
            else:
                print(colored("❌ Noto'g'ri tanlov!", 'red'))
        except KeyboardInterrupt:
            return None

# ═══════════════════════════════════════════════════════════════════════════════
# MENYU AMALLARI
# ═══════════════════════════════════════════════════════════════════════════════

def run_code_analysis():
    root_folder = show_path_menu("TAHLIL UCHUN PAPKA TANLASH")
    if root_folder is None:
        return
    root_folder = os.path.abspath(root_folder)
    if not os.path.isdir(root_folder):
        print(colored(f"❌ '{root_folder}' papkasi mavjud emas!", 'red'))
        return

    output_folder = os.path.join(root_folder, "CodeAnalyzer_Results")
    os.makedirs(output_folder, exist_ok=True)

    print(colored(f"\n🔍 Tahlil qilinmoqda: {root_folder}", 'cyan', attrs=['bold']))
    print(colored(f"📁 Chiqish papkasi: {output_folder}", 'cyan'))

    git_info = get_git_info(root_folder) if not PLATFORM_INFO['is_android'] else {'is_git_repo': False}
    if git_info.get('is_git_repo'):
        print(colored(f"✅ Git: {git_info.get('branch', 'N/A')}", 'green'))

    system_stats = get_system_stats()
    structure, stats, extensions = analyze_folder(root_folder, verbose=False)

    if not structure:
        print(colored("\n⚠️  Tahlil qilinadigan fayllar topilmadi!", 'yellow'))
        return

    print(colored("\n📊 TAHLIL NATIJALARI:", 'cyan', attrs=['bold']))
    print(colored("═" * 50, 'white'))
    print(colored(f"  ✅ Qayta ishlangan: {stats.processed_files}", 'green'))
    print(colored(f"  📝 Jami qatorlar: {stats.total_lines:,}", 'green'))
    print(colored(f"  💾 Jami hajm: {stats.total_size / (1024*1024):.2f} MB", 'green'))
    print(colored(f"  ⏱️  Vaqt: {stats.duration_seconds():.2f} sekund", 'green'))
    if stats.skipped_files > 0:
        print(colored(f"  ⏭️  Skip qilingan: {stats.skipped_files}", 'yellow'))
    if stats.error_files > 0:
        print(colored(f"  ❌ Xatoliklar: {stats.error_files}", 'red'))

    if extensions:
        print(colored(f"\n📌 TOPILGAN FAYL TURLARI ({len(extensions)} ta):", 'cyan'))
        top_exts = sorted(stats.file_types.items(), key=lambda x: x[1]['count'], reverse=True)[:10]
        for ext, data in top_exts:
            print(colored(f"  • .{ext} - {data['count']} ta fayl", 'white'))

    print(colored("\n💾 Natijalar saqlanmoqda...", 'yellow'))

    # JSON (asl tartibda)
    json_file = os.path.join(output_folder, 'project_structure.json')
    ordered_json = {
        'metadata': {
            'generated': datetime.now().isoformat(),
            'root_folder': root_folder,
            'platform': PLATFORM_INFO,
            'system_stats': asdict(system_stats),
            'stats': asdict(stats),
            'git_info': git_info,
        },
        'structure': structure
    }
    with open(json_file, 'w', encoding='utf-8') as f:
        json.dump(ordered_json, f, indent=4, ensure_ascii=False, sort_keys=False, default=str)
    print(colored(f"  ✅ JSON: {os.path.basename(json_file)}", 'green'))

    # HTML
    html_file = generate_html_report(stats, git_info, output_folder, structure, system_stats)
    print(colored(f"  ✅ HTML: {os.path.basename(html_file)}", 'green'))

    # TXT
    saved_files = write_output_files_ordered(structure, output_folder)
    print(colored(f"\n📄 YARATILGAN TXT FAYLLAR ({len(saved_files)} ta):", 'green', attrs=['bold']))
    for i, filepath in enumerate(saved_files, 1):
        filename = os.path.basename(filepath)
        size = os.path.getsize(filepath) / 1024
        print(colored(f"  {i}. {filename} ({size:.1f} KB)", 'white'))

    print(colored("\n✨ TAHLIL MUVOFFAQIYATLI YAKUNLANDI!", 'green', attrs=['bold']))
    print(colored(f"📂 Natijalar: {output_folder}", 'cyan'))

def run_tree_generator():
    print(colored("\n🌳 TREE GENERATOR", 'cyan', attrs=['bold']))
    root_path = show_path_menu("DARAXT UCHUN PAPKA TANLASH")
    if root_path is None:
        return

    try:
        depth_input = input(colored("📏 Maksimal chuqurlik (default 5): ", 'green')).strip()
        max_depth = int(depth_input) if depth_input else 5
    except ValueError:
        max_depth = 5

    unicode_input = input(colored("🎨 Unicode belgilar (ha/yo'q, default ha): ", 'green')).strip().lower()
    use_unicode = unicode_input not in ["yo'q", 'no', 'n', '0']

    print(colored(f"\n🌳 Daraxt yaratilmoqda: {root_path}", 'cyan'))
    try:
        tree_text = generate_tree_from_folder(root_path, max_depth=max_depth, use_unicode=use_unicode)
        print(colored("\n" + "═" * 60, 'white'))
        print(tree_text)
        print(colored("═" * 60, 'white'))

        save = input(colored("\n💾 Faylga saqlansinmi? (ha/yo'q, default ha): ", 'green')).strip().lower()
        if save not in ["yo'q", 'no', 'n', '0']:
            output_dir = os.path.join(root_path, 'CodeAnalyzer_Results')
            os.makedirs(output_dir, exist_ok=True)
            out_file = os.path.join(output_dir, 'project_tree.txt')
            with open(out_file, 'w', encoding='utf-8') as f:
                f.write(f"{'='*80}\nTREE GENERATOR\nRoot: {root_path}\n")
                f.write(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n{'='*80}\n\n")
                f.write(tree_text)
            print(colored(f"✅ Saqlandi: {out_file}", 'green'))
    except Exception as e:
        print(colored(f"❌ Xato: {e}", 'red'))

def run_project_generator():
    print(colored("\n🏗️  UNIVERSAL PROJECT GENERATOR", 'cyan', attrs=['bold']))
    print(colored("═" * 60, 'yellow'))
    print()
    print(colored("  Qaysi formatdan foydalanasiz?", 'white'))
    print(colored("  1️⃣  JSON", 'white'))
    print(colored("  2️⃣  YAML" + ("" if HAS_YAML else " (o'rnatilmagan — pip install pyyaml)"), 'white'))
    print(colored("  3️⃣  TREE (matn daraxti)", 'white'))
    print(colored("  0️⃣  Orqaga", 'red'))
    print()

    choice = input(colored("➡️  Tanlang (1/2/3/0): ", 'green')).strip()
    if choice == '0':
        return
    if choice not in ['1', '2', '3']:
        print(colored("❌ Noto'g'ri tanlov!", 'red'))
        return
    if choice == '2' and not HAS_YAML:
        print(colored("❌ YAML uchun PyYAML o'rnatilmagan. `pip install pyyaml`", 'red'))
        return

    print(colored(f"\n📝 Namuna fayl yaratilmoqda...", 'yellow'))
    sample_path = write_sample_file(choice, ".")
    if sample_path:
        print(colored(f"✅ Namuna yaratildi: {sample_path}", 'green'))
        print(colored("💡 Endi uni tahrirlang va quyida yo'lini kiriting.", 'yellow'))

    file_path = input(colored("\n📁 Struktura fayli yo'lini kiriting: ", 'green')).strip()
    if not file_path:
        print(colored("❌ Yo'l kiritilmadi!", 'red'))
        return
    if not os.path.isfile(file_path):
        print(colored(f"❌ '{file_path}' fayl topilmadi!", 'red'))
        return

    try:
        structure = load_structure_from_file(file_path, choice)
    except Exception as e:
        print(colored(f"❌ Faylni o'qishda xato: {e}", 'red'))
        return

    root = input(colored("📝 Root papka nomi (default: project_root): ", 'green')).strip() or "project_root"
    output_base = input(colored("📁 Qayerga yaratilsin? (default: joriy papka): ", 'green')).strip() or "."
    target = os.path.join(output_base, root)

    print(colored(f"\n🏗️  Loyiha yaratilmoqda: {target}", 'cyan', attrs=['bold']))
    try:
        created = create_structure(target, structure)
        print(colored(f"\n✅ Loyiha muvaffaqiyatli yaratildi!", 'green', attrs=['bold']))
        print(colored(f"📂 Joylashuv: {target}", 'cyan'))
        print(colored(f"📄 Yaratilgan elementlar: {len(created)} ta", 'green'))
    except Exception as e:
        print(colored(f"❌ Xato: {e}", 'red'))
        return

    show = input(colored("\n🌳 TREE ko'rinishini ko'rsataymi? (ha/yo'q): ", 'green')).strip().lower()
    if show in ['ha', 'h', 'yes', 'y']:
        print(colored("\n" + "═" * 60, 'white'))
        print(dict_to_tree(structure))
        print(colored("═" * 60, 'white'))

def run_demo_generator():
    print(colored("\n🎯 DEMO GENERATOR", 'cyan', attrs=['bold']))
    print(colored("  1. Barcha demo fayllar (Python + Web + Data)", 'white'))
    print(colored("  2. Faqat Python demo", 'white'))
    print(colored("  3. Faqat Web demo (HTML/CSS/JS)", 'white'))
    print(colored("  4. Faqat Data demo (CSV, TXT, SQL)", 'white'))
    print(colored("  0. Orqaga", 'red'))
    type_map = {'1': 'all', '2': 'python', '3': 'web', '4': 'data'}
    choice = input(colored("\n➡️  Tanlang (1-4/0): ", 'green')).strip()
    if choice == '0':
        return
    if choice not in type_map:
        print(colored("❌ Noto'g'ri tanlov!", 'red'))
        return
    output_dir = input(colored("📁 Qayerga yaratilsin? (default: joriy papka): ", 'green')).strip() or os.getcwd()
    created = generate_demo_files(output_dir, type_map[choice])
    print(colored(f"\n✅ {len(created)} ta demo fayl yaratildi!", 'green', attrs=['bold']))
    print(colored(f"📂 Joylashuv: {output_dir}", 'cyan'))
    for item in created:
        print(colored(f"  {item}", 'white'))

def run_system_stats():
    stats = get_system_stats()
    print_system_stats(stats, verbose=True)

# ═══════════════════════════════════════════════════════════════════════════════
# UTILIT FUNKSIYALAR
# ═══════════════════════════════════════════════════════════════════════════════

def clear_screen():
    os.system('cls' if PLATFORM_INFO['is_windows'] else 'clear')

def print_banner():
    clear_screen()
    if PLATFORM_INFO['is_windows']:
        os.system('color')
    banner = pyfiglet.figlet_format("CODE ANALYZER", font='slant')
    print(colored(banner, 'red'))
    platform_str = "WINDOWS" if PLATFORM_INFO['is_windows'] else \
                   "LINUX" if PLATFORM_INFO['is_linux'] else \
                   "MAC OS" if PLATFORM_INFO['is_mac'] else \
                   "ANDROID (PyDroid3)" if PLATFORM_INFO['is_android'] else "UNKNOWN"
    print(colored("╔" + "═" * 68 + "╗", 'yellow'))
    print(colored("║" + " PRO ULTIMATE EDITION v3 - Cross-Platform".center(68) + "║", 'cyan'))
    print(colored("║" + f" Platforma: {platform_str}".center(68) + "║", 'green'))
    print(colored("║" + " Analysis | Tree | Project | Demo | Stats".center(68) + "║", 'green'))
    print(colored("║" + " Universal Generator (TREE/JSON/YAML)".center(68) + "║", 'green'))
    print(colored("╠" + "═" * 68 + "╣", 'yellow'))
    print(colored("║  📧 prodevuzoff@gmail.com".ljust(69) + "║", 'blue'))
    print(colored("║  📱 @otaboyev_sardorbek_blog_dev".ljust(69) + "║", 'cyan'))
    print(colored("║  🐙 github.com/otaboyevsardorbek1/CodeAnalyzer".ljust(69) + "║", 'yellow'))
    print(colored("╚" + "═" * 68 + "╝", 'yellow'))
    print()

def setup_logging(output_folder: str, verbose: bool = False) -> None:
    log_file = os.path.join(output_folder, 'code_analyzer.log')
    level = logging.DEBUG if verbose else logging.INFO
    logging.basicConfig(
        level=level,
        format='%(asctime)s - %(levelname)-8s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S',
        handlers=[
            logging.FileHandler(log_file, encoding='utf-8'),
            logging.StreamHandler(sys.stdout) if verbose else logging.NullHandler()
        ]
    )

def parse_arguments():
    parser = argparse.ArgumentParser(
        description='Code Analyzer Pro Ultimate Edition v3 - Cross-Platform',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
📌 MISOL:
  python CodeAnalyzer_v3.py
  python CodeAnalyzer_v3.py /path/to/project
  python CodeAnalyzer_v3.py /path/to/project -o ./analysis -v
  python CodeAnalyzer_v3.py /path/to/project --max-size 100 --workers 4
  python CodeAnalyzer_v3.py /path/to/project --no-html --no-txt

📋 PLATFORMALAR:
  ✅ Windows 7/8/10/11
  ✅ Linux (Ubuntu, Debian, Fedora, Arch, etc)
  ✅ macOS
  ✅ Android (PyDroid3)
        """
    )
    parser.add_argument('path', nargs='?', help='Tahlil qilinadigan loyiha papkasi')
    parser.add_argument('-o', '--output', default='CodeAnalyzer_Results',
                        help='Chiqish papkasi (default: CodeAnalyzer_Results)')
    parser.add_argument('--exclude', help='Exclude patternlar (vergul bilan)')
    parser.add_argument('--max-size', type=int, default=50,
                        help='Maksimal fayl hajmi MB da (default: 50)')
    parser.add_argument('--workers', type=int, default=None,
                        help='Paralel ishchilar soni')
    parser.add_argument('-v', '--verbose', action='store_true',
                        help='Batafsil ma\'lumot')
    parser.add_argument('--no-color', action='store_true',
                        help='Rangli chiqishni o\'chirish')
    parser.add_argument('--no-html', action='store_true',
                        help='HTML hisobot yaratmaslik')
    parser.add_argument('--no-txt', action='store_true',
                        help='TXT fayllarni yaratmaslik')
    parser.add_argument('--no-system-stats', action='store_true',
                        help='Tizim statistikasini ko\'rsatmaslik')
    parser.add_argument('--no-menu', action='store_true',
                        help='Menyuni ko\'rsatmaslik (to\'g\'ridan-to\'g\'ri yo\'l so\'rash)')
    return parser.parse_args()

# ═══════════════════════════════════════════════════════════════════════════════
# ASOSIY FUNKSIYA
# ═══════════════════════════════════════════════════════════════════════════════

def main():
    args = parse_arguments()

    global colored
    if args.no_color:
        def no_color(text, *a, **k):
            return text
        colored = no_color

    print_banner()

    system_stats = get_system_stats()
    if not args.no_system_stats:
        print_system_stats(system_stats, args.verbose)

    print(colored("\n📚 KUTUBXONALAR HOLATI:", 'cyan'))
    for pkg, ver in INSTALLED_DEPS.items():
        print(colored(f"  ✅ {pkg} v{ver}", 'green'))
    if PSUTIL_AVAILABLE:
        print(colored(f"  🔵 psutil (tizim statistikasi)", 'blue'))
    else:
        print(colored(f"  ⚠️  psutil o'rnatilmagan", 'yellow'))
    if HAS_YAML:
        print(colored(f"  🔵 PyYAML (YAML support)", 'blue'))

    # CLI rejimi — path berilgan bo'lsa
    if args.path:
        try:
            root_folder = os.path.abspath(args.path)
            if not os.path.isdir(root_folder):
                print(colored(f"\n❌ '{root_folder}' papkasi mavjud emas!", 'red'))
                sys.exit(1)

            output_folder = os.path.join(root_folder, args.output)
            os.makedirs(output_folder, exist_ok=True)
            setup_logging(output_folder, args.verbose)

            print(colored(f"\n🔍 Tahlil qilinmoqda: {root_folder}", 'cyan', attrs=['bold']))
            print(colored(f"📁 Chiqish papkasi: {output_folder}", 'cyan'))

            custom_skip_patterns = []
            if args.exclude:
                custom_skip_patterns = [p.strip() for p in args.exclude.split(',')]

            git_info = get_git_info(root_folder) if not PLATFORM_INFO['is_android'] else {'is_git_repo': False}
            if git_info.get('is_git_repo'):
                print(colored(f"✅ Git repository: {git_info.get('branch', 'N/A')}", 'green'))

            structure, stats, extensions = analyze_folder(
                root_folder,
                max_workers=args.workers,
                max_size_mb=args.max_size,
                custom_skip_patterns=custom_skip_patterns,
                verbose=args.verbose
            )

            if not structure:
                print(colored("\n⚠️  Tahlil qilinadigan fayllar topilmadi!", 'yellow'))
                return

            print(colored("\n📊 TAHLIL NATIJALARI:", 'cyan', attrs=['bold']))
            print(colored("═" * 50, 'white'))
            print(colored(f"  ✅ Qayta ishlangan: {stats.processed_files}", 'green'))
            print(colored(f"  📝 Jami qatorlar: {stats.total_lines:,}", 'green'))
            print(colored(f"  💾 Jami hajm: {stats.total_size / (1024*1024):.2f} MB", 'green'))
            print(colored(f"  ⏱️  Vaqt: {stats.duration_seconds():.2f} sekund", 'green'))
            if stats.skipped_files > 0:
                print(colored(f"  ⏭️  Skip qilingan: {stats.skipped_files}", 'yellow'))
            if stats.error_files > 0:
                print(colored(f"  ❌ Xatoliklar: {stats.error_files}", 'red'))

            print(colored("\n💾 Natijalar saqlanmoqda...", 'yellow'))

            # JSON
            json_file = os.path.join(output_folder, 'project_structure.json')
            ordered_json = {
                'metadata': {
                    'generated': datetime.now().isoformat(),
                    'root_folder': root_folder,
                    'platform': PLATFORM_INFO,
                    'system_stats': asdict(system_stats) if not args.no_system_stats else {},
                    'stats': asdict(stats),
                    'git_info': git_info,
                },
                'structure': structure
            }
            with open(json_file, 'w', encoding='utf-8') as f:
                json.dump(ordered_json, f, indent=4, ensure_ascii=False,
                          sort_keys=False, default=str)
            print(colored(f"  ✅ JSON: {os.path.basename(json_file)}", 'green'))

            # HTML
            if not args.no_html:
                html_file = generate_html_report(stats, git_info, output_folder,
                                                  structure, system_stats)
                print(colored(f"  ✅ HTML: {os.path.basename(html_file)}", 'green'))

            # TXT
            if not args.no_txt:
                saved_files = write_output_files_ordered(structure, output_folder)
                print(colored(f"\n📄 YARATILGAN TXT FAYLLAR ({len(saved_files)} ta):",
                              'green', attrs=['bold']))
                for i, filepath in enumerate(saved_files, 1):
                    filename = os.path.basename(filepath)
                    size = os.path.getsize(filepath) / 1024
                    print(colored(f"  {i}. {filename} ({size:.1f} KB)", 'white'))

            print(colored("\n✨ TAHLIL MUVOFFAQIYATLI YAKUNLANDI!", 'green', attrs=['bold']))
            print(colored(f"📂 Natijalar: {output_folder}", 'cyan'))
            return

        except KeyboardInterrupt:
            print(colored("\n\n⚠️  To'xtatildi!", 'yellow'))
            return
        except Exception as e:
            print(colored(f"\n❌ Xato: {e}", 'red'))
            if args.verbose:
                import traceback
                traceback.print_exc()
            return

    # Interaktiv menyu rejimi
    try:
        while True:
            choice = show_main_menu()
            if choice is None:
                print(colored("\n❌ Dastur to'xtatildi!", 'red'))
                break
            if choice == '1':
                run_code_analysis()
            elif choice == '2':
                run_tree_generator()
            elif choice == '3':
                run_project_generator()
            elif choice == '4':
                run_demo_generator()
            elif choice == '5':
                run_system_stats()

            input(colored("\n⏎  Davom etish uchun Enter bosing...", 'yellow'))
            clear_screen()
            print_banner()
    except KeyboardInterrupt:
        print(colored("\n\n⚠️  Dastur to'xtatildi!", 'yellow'))
    except Exception as e:
        print(colored(f"\n❌ Kutilmagan xato: {e}", 'red'))
        import traceback
        traceback.print_exc()
    finally:
        print(colored("\n👨‍💻 Code Analyzer Pro v3 — Cross Platform", 'cyan'))
        print(colored(f"🖥️  Platforma: {PLATFORM_INFO['system'].upper()}", 'blue'))
        print(colored("📧 prodevuzoff@gmail.com | 📱 @otaboyev_sardorbek_blog_dev", 'blue'))
        print()

if __name__ == "__main__":
    main()
