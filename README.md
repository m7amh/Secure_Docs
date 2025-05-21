# 🔐 Secure_Docs — Advanced Secure File Storage System

> 📁 A fully secure, high-integrity file storage platform built with modern encryption, real-time integrity verification, and solid authentication control.

---

## 🎯 Project Overview

**Secure_Docs** is a full-fledged solution for secure file storage, designed to handle sensitive data with enterprise-grade protection. Built for privacy-conscious individuals, teams, and organizations, it blends cryptographic strength with usability and monitoring.

Whether you're storing confidential documents or mission-critical records, **Secure_Docs** ensures they stay **encrypted, verified, and only accessible to the right people** — always.

---

## 🛡️ Security Highlights

### 🔒 Multi-Layer Encryption
- **AES-256-CBC** for robust file encryption
- **Unique IV (Initialization Vector)** for every file
- **Password-based key derivation** using **SHA-256**
- Secure, centralized **Key Management System**

### ✅ Integrity Protection
- **HMAC-SHA256** for verifying encrypted content
- **CRC32 checksum** for post-decryption file integrity
- **RSA-2048 digital signatures** to ensure authenticity
- **Real-time status monitoring** for file integrity

### 🔐 Authentication & Access Control
- User session and identity management
- **Ownership verification** per file access
- **Role-Based Access Control (RBAC)**
- Secure password handling and hashing

---

## 🚀 Features

### 📂 File Management
- Secure file **upload** and **download**
- File integrity verification during every transaction
- **Download history** tracking
- Metadata management (filename, owner, size, timestamp)

### 🔍 Security Monitoring
- **Real-time file integrity checks**
- Status dashboard for verified/unverified files
- **Audit logging** of all user actions
- Monitoring of potential threats or anomalies

### 💻 User Interface
- Clean, responsive UI
- Smooth file interaction and navigation
- Visual security indicators (e.g., verified / tampered)
- Live feedback on upload/download status

---

## 🔄 Security Workflow

### ⬆️ Upload Flow
1. User uploads a file
2. A unique **IV** is generated
3. File is encrypted using **AES-256-CBC**
4. **HMAC** is calculated for encrypted content
5. **Digital signature (RSA)** is generated
6. File and metadata are securely stored

### ⬇️ Download Flow
1. User authenticates
2. **HMAC** is verified to ensure no tampering
3. File is decrypted
4. **CRC32** checksum is run for integrity
5. Digital signature is verified
6. File is delivered securely to the user

---

## 🔍 Security Best Practices Followed
- All sensitive data is encrypted **at rest**
- Secure and structured **key management**
- Regular integrity checks
- Audit logging for every sensitive action
- Mitigation for XSS, SQLi, CSRF, and other common vulnerabilities

---

## 💡 Why Secure_Docs?

> "Your files are gold. Secure_Docs is the vault."

- Built with academic precision, and professional foresight
- Designed for real-world use: privacy-first, user-friendly
- A tool that doesn't just store — it protects, monitors, and empowers

Whether you're an end-user or a security enthusiast, Secure_Docs gives you **trust in every byte.**

---

## 👥 Project Team

| Name                                | ID       |
|-------------------------------------|----------|
| Mohamed Abdelrahman Awad Khaled     | 2205114  |
| Mohamed Ahmed Ramadan               | 2205043  |
| Omar Ahmed Hameed                   | 2205213  |
| Mohamed Ahmed Sobhy El-Ash          | 2205044  |
| Ahmed Yasser Ahmed Mahmoud Emara    | 2205106  |

---

## 📌 Note

This project was created with a balance of:
- 🧠 Academic rigor (for top marks and strong evaluation)
- 💼 Real-world mindset (with future scalability in mind)

> "Security is not optional — it's fundamental."  
> _Secure_Docs: Because your data deserves more than storage — it deserves protection._

---

