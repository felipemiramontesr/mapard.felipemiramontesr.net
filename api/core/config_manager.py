import os
import json
from typing import Any, Dict, Optional


class ConfigManager:
    """Centralized configuration loader for MAPA-RD components.

    This class handles:
    1. Resolving the project base directory.
    2. Loading the `03_Config/config.json` file.
    3. Providing access to configuration values with environment variable overrides.
    """

    _instance = None
    _config: Dict[str, Any] = {}
    _loaded = False

    def __new__(cls):
        if cls._instance is None:
            cls._instance = super(ConfigManager, cls).__new__(cls)
        return cls._instance

    def __init__(self) -> None:
        if not self._loaded:
            self.base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            self.config_path = os.path.join(self.base_dir, "03_Config", "config.json")
            self._load_config()
            self._loaded = True

    def _load_config(self) -> None:
        """Load configuration from file and apply defaults."""
        if os.path.exists(self.config_path):
            try:
                with open(self.config_path, "r", encoding="utf-8") as f:
                    self._config = json.load(f)
            except (json.JSONDecodeError, IOError) as e:
                print(
                    f"[!] Warning: ConfigManager failed to load {self.config_path}: {e}"
                )
                self._config = {}
        else:
            self._config = {}

    def get(self, key: str, default: Any = None) -> Any:
        """Retrieve a configuration value by key."""
        return self._config.get(key, default)

    def get_section(self, section: str) -> Dict[str, Any]:
        """Retrieve a specific section of the configuration (e.g., 'email')."""
        return self._config.get(section, {})

    @property
    def project_root(self) -> str:
        """Return the absolute path to the project root directory."""
        return self.base_dir
