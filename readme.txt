=== Custom Captcha Field for Fluent Forms ===
Contributors: pyrobd
Tags: captcha, spam, fluent forms, math captcha, antispam
Requires at least: 5.0
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, self-hosted captcha field for Fluent Forms — image, math and question/answer challenges. No external services, no tracking.

== Description ==
Adds a "Custom Captcha" field to the Fluent Forms editor with three challenge types:

- **Image captcha** — a 6-character code rendered as an image, with customizable text color and a transparent background by default so it blends with any form design
- **Math captcha** — a simple arithmetic question rendered as an image
- **Question and answer** — you define the question (field label) and the expected answer

Challenges are single-use and expire after 15 minutes. Answers are stored server-side via WordPress transients — nothing is exposed to the browser except the image itself. Visitors get a refresh button to request a new challenge, and the captcha automatically renews after each AJAX submission, so it also works on cached pages.

On top of the captcha you get two extra spam defenses:

- **Time-trap** (optional, off by default) — submissions completed faster than a human possibly could are rejected via an HMAC-signed timestamp; enable it and set the minimum submit time per field
- **Disposable email blocking** (optional, on by default) — rejects submissions where an email field uses a temporary/disposable email domain like mailinator.com or yopmail.com; extend the list with the `ffc_captcha_blocked_email_domains` filter

Everything runs on your own server: no external captcha service, no API keys, no cookies, no visitor data sent anywhere.

=== Requirements ===
<a href="https://wordpress.org/plugins/fluentform">Fluent Forms</a> is required. The PHP GD extension is recommended for image rendering (with a plain-text fallback when unavailable).

== How To Use ==
1. Activate the plugin
2. Edit a form in Fluent Forms — a new "Custom Captcha" field appears in the editor
3. Drop it into your form and pick the captcha type in the field settings
4. Optionally set the text color, background color (RGB values like "68, 68, 68", or "transparent") and error message

== Installation ==
1. Login to your WordPress Admin Area
2. Go to Plugins -> Add New
3. Type "Custom Captcha Field for Fluent Forms" into the Search and hit Enter
4. Find this plugin and click "Install Now"
5. Activate the plugin
6. Add the Custom Captcha field to your form in the Fluent Forms editor

== Changelog ==
= 2.0.0 =
* Security: challenge answers are no longer stored in a publicly readable file; each challenge is now a single-use, expiring token stored via WordPress transients
* Security: removed eval() from math captcha validation
* Added: transparent captcha background (new default) and customizable text/background colors
* Added: refresh button on the captcha image
* Added: captcha auto-renews after AJAX submissions and on page load, so it works with page caching
* Added: optional time-trap — reject submissions completed faster than a configurable minimum (default 3 seconds) or carrying a tampered timestamp
* Added: optional disposable email blocking for the whole form (filterable via ffc_captcha_blocked_email_domains)
* Fixed: compatible with Fluent Forms 5+ hook names (validation no longer relies on a deprecated hook)
* Fixed: math challenges no longer produce negative answers
* Fixed: text captcha comparison is now trimmed and case-insensitive
* Fixed: field renamed from "Recaptcha" to "Custom Captcha" (this plugin is not related to Google reCAPTCHA)

= 1.0.0 =
* Initial release
