# Phase 5: Custom Email Template Builder, Live Preview & Spam Score Checker

## 1. Goal Description
Build an email template creator featuring a visual WYSIWYG rich-text editor, raw HTML code mode, dynamic personalization merge tags (`{{first_name}}`, `{{email}}`, etc.), real-time **Desktop (1200px)** and **Mobile (375px)** responsive previewers, and a **Spam Trigger Keyword Analyzer** to ensure high inbox placement.

---

## 2. Feature Specifications

### A. Rich Template Editor
- **WYSIWYG Mode**: Headings, Paragraphs, Bold/Italic, Links, Buttons, Image embedding, Dividers, Colors, and Typography formatting.
- **Code Mode**: Full syntax-highlighted HTML/CSS editor for advanced developer templates.
- **Pre-Built Starter Templates**:
  1. *Modern Newsletter* (Clean layout with header, body & social footer)
  2. *Product Promotion & Announcement* (Hero image, CTA button, offer banner)
  3. *Transactional Notification* (Clean, minimal, high-deliverability)
  4. *Personal Cold Outreach* (Plain-text styled for maximum inbox placement)

### B. Personalization Merge Tags
- Click-to-insert dynamic merge variables:
  - `{{first_name}}` -> Recipient First Name (or fallback default)
  - `{{last_name}}` -> Recipient Last Name
  - `{{name}}` -> Full Name
  - `{{email}}` -> Recipient Email Address
  - `{{company}}` -> Company Name
  - `{{unsubscribe_url}}` -> One-click direct unsubscribe link

### C. Live Responsive Dual Preview
- Real-time side-by-side or toggleable preview:
  - 🖥️ **Desktop Preview** (1200px width simulator)
  - 📱 **Mobile Preview** (375px mobile viewport frame)
- Dynamic variable rendering: Preview sample data replaced in real-time.
- "Send Test Preview" button to send the rendered template directly to your own inbox.

### D. Anti-Spam Content Analyzer & Deliverability Shield
- Real-time content inspector that checks:
  1. **Spam Trigger Word Detector**: Flags phrases known to trigger spam filters (e.g. "100% FREE", "ACT NOW", "BUY DIRECT", "MAKE MONEY FAST", excessive "$$$" or "!!!").
  2. **Text-to-Image Ratio**: Alerts if the email contains large images with little text (major spam trigger).
  3. **All-Caps Header Check**: Flags excessive uppercase letters in subject or body.
  4. **Plain-Text Alternative Generator**: Automatically creates synchronized `text/plain` version for multipart MIME delivery.

---

## 3. Step-by-Step Implementation Tasks
1. Create `email_templates` migration & `EmailTemplate` model.
2. Integrate visual editor (Quill / TinyMCE / Visual HTML component) with merge tag menu.
3. Build live dual-frame responsive preview component.
4. Implement `DeliverabilityScoreService` for spam keyword scanning and HTML/text ratio calculation.
5. Create starter template seeders.

---

## 4. Verification & Acceptance Criteria
- [ ] Template editor allows seamless visual editing and HTML source toggling.
- [ ] Merge tags dynamically populate in preview mode.
- [ ] Mobile and desktop previews reflect real viewport behavior.
- [ ] Spam score analyzer flags trigger words and displays deliverability tips.
