<?php
/**
 * أنماط احتياطية لشاشات الدخول/الاستعادة —
 * تبقى الصفحة منسّقة حتى لو تعذّر تحميل Bootstrap أو app.css بعد النقل.
 */
?>
<style id="dms-login-fallback">
.login-page {
  min-height: 100vh;
  min-height: 100dvh;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f6f2;
  font-family: 'GretaArabic', 'Amiri', Tahoma, Arial, sans-serif;
  padding: max(1rem, env(safe-area-inset-top, 0px)) max(1rem, env(safe-area-inset-right, 0px)) max(1rem, env(safe-area-inset-bottom, 0px)) max(1rem, env(safe-area-inset-left, 0px));
  box-sizing: border-box;
}
.login-card {
  width: 100%;
  max-width: min(420px, 100%);
  margin: 0;
  padding: clamp(1.25rem, 3vw, 1.75rem) clamp(1rem, 3vw, 1.5rem);
  background: #fff;
  border: 1px solid #e6e2da;
  border-top: 4px solid #3B4F6E;
  border-radius: 14px;
  box-shadow: 0 8px 24px rgba(59, 79, 110, 0.08);
  font-family: 'GretaArabic', 'Amiri', Tahoma, Arial, sans-serif;
}
.login-card .form-label { display: block; margin-bottom: .35rem; font-weight: 600; }
.login-card .form-control {
  width: 100%;
  box-sizing: border-box;
  padding: .65rem .75rem;
  min-height: 2.75rem;
  border: 1px solid #ced4da;
  border-radius: .5rem;
  font: inherit;
  font-family: inherit;
  font-size: 1rem;
}
.login-card .btn-kff {
  display: block;
  width: 100%;
  border: 0;
  border-radius: .5rem;
  padding: .75rem 1rem;
  min-height: 2.75rem;
  background: #3B4F6E;
  color: #fff !important;
  text-decoration: none !important;
  text-align: center;
  font: inherit;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  box-sizing: border-box;
}
.login-card .btn-outline-secondary {
  display: block;
  width: 100%;
  border: 1px solid #ced4da;
  border-radius: .5rem;
  padding: .65rem 1rem;
  min-height: 2.5rem;
  background: #fff;
  color: #3B4F6E;
  text-decoration: none !important;
  text-align: center;
  font: inherit;
  box-sizing: border-box;
}
.login-card .mb-3 { margin-bottom: 1rem; }
.login-card .mb-4 { margin-bottom: 1.25rem; }
.login-card .mt-2 { margin-top: .5rem; }
.login-card .text-center { text-align: center; }
.login-card .text-muted { color: #6c757d; }
.login-card .text-dark { color: #212529; }
.login-card .small { font-size: .875rem; }
.login-card .h4, .login-card .h5 { margin: 0 0 .35rem; font-weight: 700; }
.login-logo { max-height: clamp(56px, 12vw, 72px); max-width: min(220px, 70vw); width: auto; height: auto; object-fit: contain; }
.login-pass-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: .5rem;
  margin-bottom: .35rem;
}
.login-pass-row .form-label { margin-bottom: 0; }
.login-lang {
  position: absolute;
  top: 0;
  inset-inline-end: 0;
  padding: 1rem;
  z-index: 5;
}
.login-lang .btn-group { display: inline-flex; }
.login-lang .btn {
  display: inline-block;
  padding: .25rem .55rem;
  min-height: auto;
  width: auto;
  border: 1px solid #ced4da;
  background: #fff;
  color: #3B4F6E;
  text-decoration: none;
  font-size: .85rem;
}
.login-lang .btn-kff { background: #3B4F6E; color: #fff; border-color: #3B4F6E; }
.login-lang .btn:first-child { border-start-start-radius: .35rem; border-end-start-radius: .35rem; }
.login-lang .btn:last-child { border-start-end-radius: .35rem; border-end-end-radius: .35rem; }
@media (max-width: 576px) {
  .login-page { align-items: flex-start; padding-top: max(2rem, env(safe-area-inset-top, 0px)); }
}
</style>
