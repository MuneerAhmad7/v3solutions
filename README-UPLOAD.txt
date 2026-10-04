V3 SOLUTIONS — STATIC 3D WEBSITE (HTML / CSS / JavaScript)
==========================================================

HOW TO UPLOAD (cPanel)
1. cPanel > File Manager > open public_html
2. Upload this ZIP and click "Extract" (all files go directly into public_html)
   Make sure hidden files are shown so ".htaccess" is included.
3. Open your domain — done.

FORMS (contact, consultation, careers CV upload, newsletter)
- Handled by send.php using your host's PHP mail().
- Open send.php and edit the SETTINGS block at the top:
    TO_EMAIL, CAREERS_EMAIL, FROM_EMAIL (must be an address on your own domain).
- Create that FROM_EMAIL mailbox in cPanel > Email Accounts for best deliverability.

AFTER SSL IS ACTIVE
- In .htaccess, uncomment the "Force HTTPS" lines and the HSTS header.

DOMAIN
- sitemap.xml, robots.txt and page canonical tags use https://v3solutions.dev
  Find & replace this if your domain is different.

STRUCTURE
- 16 pages (index, about, services, 4 service practices, solutions, projects,
  insights, careers, contact, security-notice, privacy, terms, 404)
- assets/css/style.css   design system + CSS 3D effects
- assets/js/main.js      interactions, modals, demo dashboards, forms
- assets/js/scene3d.js   Three.js 3D hero core, globe, page backgrounds
- assets/js/three.module.min.js  (local copy — no CDN needed)
- assets/js/data.js      demos, case studies, articles, jobs content
- assets/img/            optimized WebP images
