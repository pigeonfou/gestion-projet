# OddWorks Debian package

Build on an Ubuntu/Debian machine with `dpkg-deb`:

```bash
bash packaging/debian/build.sh 1.0.0
```

Copy `dist/oddworks_1.0.0_all.deb` to Ubuntu 24.04 or 26.04. No GitHub connection is required on the target machine.

```bash
sudo apt install ./oddworks_1.0.0_all.deb
sudo oddworks-configure
```

The package ships the application under `/usr/share/oddworks` and stores SQLite under `/var/lib/oddworks/database.sqlite`. Removing the package does not automatically remove the database.

This first package is a **staging package**, not yet a fully configured web installer: Nginx and PHP-FPM need dedicated, version-compatible configuration and verification before serving traffic. The package intentionally does not alter existing virtual hosts or start a new web service. PHP versions come from the target Ubuntu distribution; no PHP 8.5 requirement is hardcoded.

The Outlook .msg export requires a separately provisioned Python environment with `aspose-email-foss`. It is not yet included in this package. No Cloudflare, GitHub runner or OneForAll runtime dependency.
