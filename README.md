# Bulk Domain Availability
Simple tools for create your own unlimited bulk domain checker

## Requirements
| Require | Minimum Version | Recomended Version |
| ------ | ------ | ------ |
| PHP | v5.3 | v7.1 |
| php-intl | - | - |
| php-curl | - | - |

Without composer, without database

# Notes
If you are getting the error:
> `Fatal error: Call to undefined function Pdp\idn_to_ascii()`
> on .../bulk-domain-availability/vendor/jeremykendall/php-domain-parser/src/Pdp/Parser.php: 318
Be sure to enable the php extension called intl as the domain parsing extension requires it!

## Project Installation
- Clone the repository `$ git clone https://github.com/abaron/bulk-domain-availability.git`
- RUN. Enjoy

## Feature
- Bulk checker availability
- Pause
- Resume
- Stop
- Combine a word
- Export to CSV
- ???

## Screenshot
[![Abaron Bulk Domain Availability](https://github.com/abaron/bulk-domain-availability/raw/master/screenshot.png)](#)

## TODO
- [x] Bulk checker
- [x] Whois
- [x] Combine
- [x] Stop, Pause, Resume
- [x] Retry
- [x] Export
- [ ] Send to email
- [ ] Remaining time
- [ ] Performance
- [ ] Unit test
- [ ] Architecture
- [ ] Design pattern
- [ ] Docs

Thank you
