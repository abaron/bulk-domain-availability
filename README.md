# Bulk Domain Availability

A fast, lightweight, and modern bulk domain availability search tool and WHOIS/RDAP inspector built with PHP and JavaScript. Designed for domain investors, developers, and naming strategists to check thousands of domain variations with zero database requirements.

[![Supported Extensions](https://img.shields.io/badge/Supported%20Extensions-2%2C088%2B%20TLDs-2ea043?style=for-the-badge)](misc/servers/servers.json)
[![Resolver Protocol](https://img.shields.io/badge/Protocols-WHOIS%20%7C%20RDAP-blue?style=for-the-badge)](#hybrid-resolver-architecture-whois--rdap)
[![PHP Version](https://img.shields.io/badge/PHP-8.2.35%20%28Min%207.4%29-777bb4?style=for-the-badge&logo=php&logoColor=white)](#system-requirements)
[![License](https://img.shields.io/badge/License-MIT-orange?style=for-the-badge)](LICENSE)

---

## Workspace Preview

![Bulk Domain Availability Interface](images/screenhot-01.avif)

---

## Overview

Bulk Domain Availability provides an all-in-one domain research workspace. It combines traditional RFC 3912 WHOIS socket querying with modern ICANN RDAP (Registration Data Access Protocol) over HTTPS, giving your tool universal coverage across classic TLDs, multi-level ccTLDs, modern gTLDs, and non-Latin Internationalized Domain Names (IDNs).

> [!IMPORTANT]
> ### Universal Coverage: 2,088+ Domain Extensions Supported
> Our tool comes preloaded with direct resolution rules for **2,088 domain extensions**, making it one of the most comprehensive self-hosted bulk search tools available:
> - **Classic & Global TLDs (gTLDs):** `.com`, `.net`, `.org`, `.xyz`, `.io`, `.co`, `.ai`, etc.
> - **Country Code TLDs & Multi-Level SLDs:** `.id`, `.co.id`, `.ai.id`, `.co.uk`, `.com.au`, `.ca`, `.de`, etc.
> - **Modern HTTPS-Native Registries (RDAP):** Google Registry domains (`.app`, `.dev`, `.page`, `.how`) and other modern TLDs without legacy port 43 servers.
> - **150+ Internationalized Domain Names (IDN):** Full native Unicode rendering for non-Latin scripts such as `.한국` (Korea), `.ไทย` (Thailand), `.рф` (Russia), `.在线` (Online), `.みんな` (Everyone), and many more.

---

## Key Features

- **Extensive Extension Database (2,088+ TLDs & SLDs):**
  Preconfigured support for over **2,088** top-level and second-level domain extensions, including `.com`, `.net`, `.org`, country-code extensions, and multi-level ccTLDs like `.ai.id`, `.co.id`, `.co.uk`, and `.com.au`.

- **Hybrid Resolver Architecture (WHOIS + RDAP):**
  - **WHOIS Socket (Port 43):** Direct, lightweight TCP socket queries with strict connection timeouts.
  - **RDAP over HTTPS:** Native support for modern registries without standard port 43 servers (such as Google Registry's `.app`, `.dev`, `.page`, `.how`) and automatic global fallback via RFC 7483/9083.

- **Internationalized Domain Names (IDN) & Native Unicode:**
  Full support for international extensions in their native scripts (e.g., `.한국`, `.ไทย`, `.рф`, `.在线`, `.みんな`). The user interface displays readable Unicode characters on selection chips and accepts both native Unicode and Punycode (`xn--...`) inputs.

- **Automated Name Combinator & Permutations:**
  Generate dozens or hundreds of target domains on the fly using prefixes, suffixes, minimum/maximum character length limits, and word-pairing combinations (with or without hyphens).

- **Asynchronous Execution & Queue Control:**
  Non-blocking batch queries powered by the browser `fetch` API and `AbortController`. Control your scan lifecycle at any point using **Pause**, **Resume**, **Stop**, or individual/bulk **Retry** for failed requests.

- **Client-Side Caching:**
  Built-in `localStorage` caching ensures previously checked domains return instantly during exploratory sessions, saving bandwidth and preventing registry rate limiting.

- **Integrated WHOIS & RDAP Viewer:**
  Inspect registration status, registrars, creation dates, expiration dates, and nameservers in an expandable drawer without leaving your search results.

- **Clean CSV Export:**
  Export availability tables, character lengths, execution times, and WHOIS sources directly into a standard CSV file with a single click.

- **Zero Database Requirement:**
  Runs immediately out of the box without MySQL, PostgreSQL, or Redis.

---

## How It Works

```
                        +----------------------------+
                        |  User Domain Input / Batch |
                        +--------------+-------------+
                                       |
                                       v
                        +----------------------------+
                        |  IDN & Unicode Normalizer  |
                        | (Punycode / Suffix Resolve)|
                        +--------------+-------------+
                                       |
                                       v
                      /--------------------------------\
                     /  Is Extension WHOIS or RDAP?     \
                    +------------------+-----------------+
                     \                 |                 /
                      \ RDAP           | WHOIS          /
                       v               |               v
            +--------------------+     |    +--------------------+
            | RDAP Query (HTTPS) |     |    | WHOIS Socket (:43) |
            | RFC 7483 / 9083    |     |    | Timeout: 4 seconds |
            +---------+----------+     |    +---------+----------+
                      |                |              |
                      |                |    (If socket fails or times out)
                      |                +------------->| (RDAP Fallback)
                      |                               |
                      v                               v
            +--------------------+          +--------------------+
            | HTTP 404: Free     |          | Pattern Matching   |
            | HTTP 200: Taken    |          | "NOT FOUND" Check  |
            +---------+----------+          +---------+----------+
                      \                               /
                       \                             /
                        v                           v
                        +---------------------------+
                        | JSON API Response to UI   |
                        +---------------------------+
```

1. **Normalization:** The incoming domain name is parsed using the Public Suffix List (PDP) and hierarchical matching. Non-ASCII international characters are converted to Punycode using PHP `intl`.
2. **Resolver Resolution:** The domain suffix is mapped against `misc/servers/servers.json`. If an extension is flagged with `protocol: rdap`, an HTTPS request is dispatched directly.
3. **Execution & Fallback:** If configured for WHOIS, a port 43 TCP socket is opened. If the socket connection fails, times out, or the registry does not operate a traditional WHOIS server, your resolver seamlessly falls back to RDAP.
4. **Availability Determination:**
   - **RDAP:** HTTP `404` indicates the domain is available; HTTP `200` indicates it is registered.
   - **WHOIS:** Case-insensitive pattern matching evaluates registry responses against designated "not found" signatures.

---

## System Requirements

| Requirement | Minimum Version | Recommended Version |
| :--- | :--- | :--- |
| **PHP** | 7.4 | 8.2.35 |
| **php-intl** | Required for IDN/Punycode domain parsing | Enabled |
| **php-curl** | Required for RDAP HTTPS queries | Enabled |
| **php-json** | Built-in | Enabled |

---

## Quick Start & Installation

### 1. Clone the Repository
```bash
git clone https://github.com/abaron/bulk-domain-availability.git
cd bulk-domain-availability
```

### 2. Verify PHP Extensions
Ensure that `curl` and `intl` are enabled in your PHP configuration:
```bash
php -m | grep -E "curl|intl"
```

### 3. Run the Application
You can run your application immediately using PHP's built-in development server:
```bash
php -S localhost:8000
```
Open your browser and navigate to `http://localhost:8000`.

---

## Web Server Deployment

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.example;
    root /path/to/bulk-domain-availability;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

### Apache Configuration
Standard PHP Apache setups will work out of the box with `index.php` as DirectoryIndex.

---

## Server Configuration Schema

Extension definitions are maintained in [misc/servers/servers.json](file:///Volumes/Shared/Works/abaron/bulk-domain-availability/misc/servers/servers.json).

### WHOIS Server Entry
```json
"id": {
    "server": "whois.id",
    "not_found": "DOMAIN NOT FOUND"
}
```

### RDAP Server Entry
```json
"app": {
    "protocol": "rdap",
    "server": "https://pubapi.registry.google/rdap/domain/",
    "not_found": "not found"
}
```

---

## Internal API Specification

The application communicates via asynchronous AJAX POST requests to `/index.php`.

### Request Headers
```http
Content-Type: application/x-www-form-urlencoded;charset=UTF-8
X-Requested-With: XMLHttpRequest
```

### 1. Check Availability
**Payload:**
```
domain=example.com
```

**Success Response:**
```json
{
    "code": 200,
    "status": "OK",
    "message": "",
    "data": {
        "domain": "example.com",
        "is_available": false,
        "execute_time": "0.14",
        "execute_time_unit": "s",
        "whois_server": "whois.verisign-grs.com"
    }
}
```

### 2. Retrieve WHOIS / RDAP Data
**Payload:**
```
domain=example.com&whois=1
```

**Success Response:**
```json
{
    "code": 200,
    "status": "OK",
    "message": "",
    "data": {
        "domain": "example.com",
        "whois_data": "Domain Name: EXAMPLE.COM\nRegistry Domain ID: ...",
        "execute_time": "0.22",
        "execute_time_unit": "s",
        "whois_server": "whois.verisign-grs.com"
    }
}
```

---

## Troubleshooting

### Error: `Fatal error: Call to undefined function idn_to_ascii()`
This indicates the PHP `intl` extension is not enabled.
- **Ubuntu / Debian:** `sudo apt-get install php-intl && sudo systemctl restart php-fpm`
- **macOS (Homebrew):** Included by default in `brew install php`. Ensure it is not commented out in `php.ini`.
- **Windows:** Uncomment `extension=intl` in your `php.ini` file.

### Port 43 Connection Timeouts
Certain cloud providers or hosting environments block outgoing TCP traffic on port 43.
- Our tool includes an automatic 4-second socket timeout protection.
- When port 43 is unreachable, your resolver automatically initiates an HTTPS RDAP query fallback, ensuring availability checks still complete successfully.

---

## License

This project is open-source software licensed under the [MIT License](file:///Volumes/Shared/Works/abaron/bulk-domain-availability/LICENSE).
