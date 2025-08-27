ZeptoMail Mailer
======================

Provides [ZeptoMail](https://www.zoho.com/zeptomail/) integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=zoho+api://TLD:KEY@default

#API with options

MAILER_DSN=zoho+api://TLD:KEY@default?track_clicks=true&track_opens=true
```

where:
 - `TLD` is your regions Zoho Mail API domain (e.g., `eu` or `com`)
 - `KEY` is your Zoho Mail API key.

Resources
---------

 * [ZeptoMail](https://www.zoho.com/zeptomail/email-api.html)
 * [Documentation](https://www.zoho.com/zeptomail/help/api/email-sending.html)
 * [Report issues](https://github.com/zambodaniel/symfony-zeptomail-mailer/issues) and
   [send Pull Requests](https://github.com/zambodaniel/symfony-zeptomail-mailer/pulls)
