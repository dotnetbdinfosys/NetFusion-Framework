#!/usr/bin/env bash
# ==============================================================================
# NetFusion Framework — Universal Linux Production Deployment & Setup Script
# Supported OS : Ubuntu, Debian, CentOS, RHEL, AlmaLinux, Rocky Linux, Alpine, Arch, macOS
# Author       : Md. Atiquzzaman Ashish
# Company      : DotNetBD
# Website      : https://www.dotnetbd.com
# Copyright    : © DotNetBD. All rights reserved.
# License      : MIT
# Version      : 1.5.3
# ==============================================================================

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -f "${ROOT_DIR}/install.sh" ]; then
    exec "${ROOT_DIR}/install.sh" "$@"
fi

echo "Error: install.sh not found in ${ROOT_DIR}"
exit 1
