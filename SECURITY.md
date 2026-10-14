# Security Policy

## Supported Versions

NetFusion Framework adheres to strict semantic versioning and enterprise security maintenance policies.

| Version | Supported          | Security Fixes Status       |
| ------- | ------------------ | --------------------------- |
| 1.0.x   | :white_check_mark: | Current GA Release (Active) |
| < 1.0   | :x:                | Unsupported                 |

---

## Reporting a Vulnerability

The NetFusion Framework security team takes software security vulnerabilities seriously. We appreciate responsible disclosure to help protect users and enterprise deployments worldwide.

### How to Report

1. **Do not create public GitHub issues** for security vulnerabilities.
2. Email your findings directly to the lead security officer:
   - **Email**: `security@dotnetbd.com` / `developer@dotnetbd.com`
   - **Subject**: `[SECURITY VULNERABILITY] NetFusion Framework - <Brief Summary>`
3. Include the following details in your report:
   - Description of the vulnerability and its potential impact.
   - Exact steps or minimal Proof-of-Concept (PoC) code to reproduce the issue.
   - Affected NetFusion version, PHP version, and operating system.
   - Proposed remediation or patch (if available).

### Response Timeline

- **Initial Response**: Within 24–48 hours confirming receipt of the advisory.
- **Triage & Assessment**: Within 5 business days with vulnerability classification (CVSS).
- **Patch & Release**: Critical security patches are released promptly with accompanying security advisories and CVE requests if applicable.

---

## Security Invariants

NetFusion Framework enforces sovereign enterprise security invariants:
- Zero external runtime Composer dependencies to eliminate 3rd-party supply-chain injection attacks.
- Post-Quantum Cryptography (ML-KEM / ML-DSA) support for quantum-resistant data protection.
- Built-in OWASP L7 Web Application Firewall (WAF) and automated data masking.
- Constant-time cryptographic string comparisons for HMAC and hash verification.
