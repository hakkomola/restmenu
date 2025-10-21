<?php
session_start();
if (isset($_SESSION['restaurant_id'])) {
    header('Location: restaurants/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VovMenu - Dijital Menü & QR Sipariş Sistemi</title>
<link rel="icon" type="image/png" href="images/menufav.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>



body {
  font-family: "Poppins", sans-serif;
  background: #f8f9fa;
  scroll-behavior: smooth;
}

/* NAVBAR */
.navbar {
  box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.navbar-brand {
  font-weight: 700;
  color: #0d6efd !important;
}

/* HERO */
.hero {
  position: relative;
  background: linear-gradient(to bottom right, rgba(13,110,253,0.75), rgba(0,0,0,0.6)),
              url('images/herobackground.jpeg') center/cover no-repeat;
  color: #fff;
  text-align: center;
  padding: 150px 25px 120px;
  border-radius: 0 0 40px 40px;
}
.hero h1 {
  font-weight: 800;
  font-size: 2.6rem;
}
.hero p {
  font-size: 1.1rem;
  color: #f8f9fa;
  max-width: 750px;
  margin: 15px auto 30px;
}

/* Feature Cards */
.feature-card {
  border-radius: 16px;
  background: #fff;
  padding: 30px 20px;
  text-align: center;
  box-shadow: 0 4px 14px rgba(0,0,0,0.05);
  transition: 0.25s;
}
.feature-card:hover { transform: translateY(-6px); }
.feature-card i { font-size: 2.6rem; color: #0d6efd; margin-bottom: 15px; }

/* Stats (feature-style) */
.stats-section {
  background: #f8f9fa;
  padding: 80px 0;
}
.stat-card {
  border-radius: 16px;
  background: #fff;
  padding: 35px 25px;
  text-align: center;
  box-shadow: 0 4px 14px rgba(0,0,0,0.05);
  transition: 0.25s;
}
.stat-card:hover { transform: translateY(-6px); }
.stat-card h2 {
  font-size: 2.4rem;
  color: #0d6efd;
  font-weight: 700;
}
.stat-card p {
  margin: 0;
  color: #555;
  font-weight: 500;
}

/* Plans */
.plan-card {
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 6px 20px rgba(0,0,0,0.05);
  padding: 35px 25px;
  text-align: center;
  transition: all 0.3s ease;
}
.plan-card:hover { transform: translateY(-6px); }
.price { font-size: 1.6rem; font-weight: 700; color: #0d6efd; }

/* CTA band */
.cta-band {
  background: #0d6efd;
  color: #fff;
  text-align: center;
  padding: 50px 20px;
  border-radius: 20px;
  margin: 70px auto;
}
.cta-band h3 { font-weight: 600; font-size: 1.8rem; }

/* About & Contact */
.about-section, .contact-section {
  background: #fff;
  padding: 80px 20px;
  border-top: 1px solid #e5e5e5;
}
.about-section h2, .contact-section h2 {
  font-weight: 700;
  color: #0d6efd;
}
.contact-box {
  border-radius: 16px;
  background: #f8f9fa;
  padding: 30px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  transition: 0.25s;
}
.contact-box:hover { transform: translateY(-5px); }

/* Footer */
footer {
  margin-top: 60px;
  padding: 25px 15px;
  text-align: center;
  background: #fff;
  color: #444;
  box-shadow: 0 -2px 6px rgba(0,0,0,0.05);
  border-top: 1px solid #e5e5e5;
}
footer i { font-size: 1.2rem; margin: 0 6px; color: #0d6efd; }
.navbar .nav-link.active {
  color: #0d6efd !important;
  font-weight: 600;
  border-bottom: 2px solid #0d6efd;
}
.stat-card i {
  transition: transform 0.3s ease;
}
.stat-card:hover i {
  transform: scale(1.1);
}

/* Mobilde giriş / üye ol butonlarını hizala ve eşitle */
@media (max-width: 991px) {
  .navbar .btn {
    display: block;
    width: 100%;
    margin: 6px 0 !important;
    font-size: 1rem;
    padding: 10px 0;
  }
}

/* Buton boylarını ve hizasını eşitle (masaüstü dahil) */
.navbar .btn {
  font-weight: 600;
  padding: 8px 16px;
  line-height: 1.2;
}
.navbar .btn-outline-primary {
  border-width: 2px;
}
.navbar .btn-primary {
  border-width: 2px;
}

/* HERO butonlarını eşitle */
.hero .btn {
  min-width: 220px; /* aynı genişlik */
  font-weight: 600;
  padding: 12px 24px; /* aynı yükseklik */
  border-width: 2px; /* outline olanla eşitleme */
}

.hero .btn-outline-light {
  color: #fff;
  background-color: transparent;
  border-color: #fff;
}

.hero .btn-outline-light:hover {
  background-color: #fff;
  color: #0d6efd;
}


</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
  <i class="bi bi-qr-code me-2 text-primary fs-3"></i>
  <span class="fw-bold" style="font-size:1.5rem;">Vov<span class="text-primary">Menu</span></span>
</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item"><a class="nav-link" href="#features">Özellikler</a></li>
        <li class="nav-item"><a class="nav-link" href="#pricing">Fiyatlandırma</a></li>
        <li class="nav-item"><a class="nav-link" href="#about">Hakkımızda</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">İletişim</a></li>
        <li class="nav-item"><a class="btn btn-outline-primary btn-sm ms-3" href="restaurants/login.php">Giriş</a></li>
        <li class="nav-item"><a class="btn btn-primary btn-sm ms-2" href="restaurants/register.php">Üye Ol</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="hero">
  <div class="container">
    <h1>Siz Hala QR Menünüzü Oluşturmadınız mı?</h1>
    <p>Menünüzü dijitale taşıyın, siparişli veya sade menü seçenekleriyle fark yaratın.  
    VovMenu, dijital menü çözümlerinde profesyonel deneyimiyle yanınızda.</p>
    <a href="restaurants/register.php" class="btn btn-light btn-lg"><i class="bi bi-rocket-takeoff"></i> QR Menü İstiyorum</a>
    <a href="restaurant_info.php?hash=16187f14a8cbc3d54ef45471&theme=dark" class="btn btn-outline-light btn-lg"><i class="bi bi-book"></i> Örnek Menüyü Gör</a>
  </div>
</div>

<!-- ÖZELLİKLER -->
<section class="container py-5" id="features">
  <div class="text-center mb-5">
    <h2 class="fw-bold">VovMenu ile Dijitale Geçin</h2>
    <p class="text-muted">10 yılı aşkın deneyim, profesyonel altyapı ve şimdi Resmî Gazete uyumluluğu.</p>
  </div>
  <div class="row g-4">
    <div class="col-md-3 col-6"><div class="feature-card"><i class="bi bi-tools"></i><h5>Ücretsiz Kurulum</h5><p>Kurulum ve destek bizden.</p></div></div>
    <div class="col-md-3 col-6"><div class="feature-card"><i class="bi bi-qr-code"></i><h5>QR Kod Kolaylığı</h5><p>Masalara özel kod üretin.</p></div></div>
    <div class="col-md-3 col-6"><div class="feature-card"><i class="bi bi-phone"></i><h5>Mobil Uyumlu</h5><p>Her cihazda mükemmel görünüm.</p></div></div>
    <div class="col-md-3 col-6"><div class="feature-card"><i class="bi bi-cash-stack"></i><h5>Anında Güncelleme</h5><p>Fiyat değişikliğini anında yansıtın.</p></div></div>
  </div>
</section>

<!-- İSTATİSTİKLER -->
<!-- İSTATİSTİKLER -->
<section class="stats-section">
  <div class="container">
    <div class="row g-4 justify-content-center text-center">
      
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <i class="bi bi-people-fill text-primary fs-1 mb-2"></i>
          <h2 class="count" data-target="125">0</h2>
          <p>Aktif Kullanıcı</p>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <div class="stat-card">
          <i class="bi bi-shop text-primary fs-1 mb-2"></i>
          <h2 class="count" data-target="87">0</h2>
          <p>Restoran Kullanıyor</p>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <div class="stat-card">
          <i class="bi bi-list-check text-primary fs-1 mb-2"></i>
          <h2 class="count" data-target="642">0</h2>
          <p>Menü Oluşturuldu</p>
        </div>
      </div>

      <div class="col-md-3 col-6">
        <div class="stat-card">
          <i class="bi bi-eye text-primary fs-1 mb-2"></i>
          <h2 class="count" data-target="12450">0</h2>
          <p>Menü Görüntülendi</p>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- FİYATLANDIRMA -->
<section class="container py-5" id="pricing">
  <div class="text-center mb-5">
    <h2 class="fw-bold">Planlar ve Fiyatlar</h2>
    <p class="text-muted">İhtiyacınıza göre sade veya siparişli menü planını seçin.</p>
  </div>
  <div class="row g-4 justify-content-center">
    <div class="col-md-5">
      <div class="plan-card">
        <h4><i class="bi bi-book me-1"></i> Sadece Menü</h4>
        <div class="price mb-2">249 TL <small class="text-muted">/ Ay</small></div>
        <div class="price mb-3 text-secondary fs-6">2.490 TL / Yıl</div>
        <ul class="list-unstyled">
          <li>QR Menü Paylaşımı</li>
          <li>Fiyat Güncelleme Kolaylığı</li>
          <li>Çoklu Dil Desteği</li>
          <li><strong>Ücretsiz e-Devlet API Entegrasyonu</strong></li>
          <p class="text-muted small mt-3">
  <i class="bi bi-shield-check text-success me-1"></i> 15 gün içinde koşulsuz iade garantisi
</p>
        </ul>
        <a href="#" class="btn btn-primary btn-lg w-100">Satın Al</a>
      </div>
    </div>
    <div class="col-md-5">
      <div class="plan-card border-primary">
        <h4><i class="bi bi-cart-check me-1"></i> Siparişli Menü</h4>
        <div class="price mb-2">449 TL <small class="text-muted">/ Ay</small></div>
        <div class="price mb-3 text-secondary fs-6">4.490 TL / Yıl</div>
        <ul class="list-unstyled">
          <li>Tüm Menü Özellikleri</li>
          <li>Online Sipariş Alabilme</li>
          <li>Masa QR Entegrasyonu</li>
          <li><strong>Ücretsiz e-Devlet API Entegrasyonu</strong></li>
          <p class="text-muted small mt-3">
  <i class="bi bi-shield-check text-success me-1"></i> 15 gün içinde koşulsuz iade garantisi
</p>
        </ul>
        <a href="#" class="btn btn-success btn-lg w-100">Satın Al</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<div class="container">
  <div class="cta-band">
    <h3>Dijital Dönüşüm Zamanı</h3>
    <p class="lead mt-2 mb-4">Binlerce restoran VovMenu ile dijitale geçti.  
      Siz de menünüzü modern, yasal ve kârlı hale getirin.</p>
    <a href="restaurants/register.php" class="btn btn-light btn-lg"><i class="bi bi-lightning-charge"></i> Hemen Başlayın</a>
  </div>
</div>

<!-- HAKKIMIZDA -->
<section id="about" class="about-section">
  <div class="container" style="max-width: 900px;">
    <h2 class="text-center mb-4">Hakkımızda</h2>
    <p class="lead text-muted text-center mb-4">
      <strong>VovMenu</strong>, 2015 yılından bu yana restoran, kafe ve otel işletmeleri için dijital menü ve sipariş sistemleri geliştiren
      köklü bir teknoloji girişimidir. Türkiye’nin dört bir yanında binlerce işletmenin dijitalleşmesine katkı sağlıyoruz.
    </p>
    <p class="text-center">
      Güçlü teknik altyapımız, kullanıcı dostu arayüzlerimiz ve 7/24 destek hizmetimizle işletmelerin dijital dönüşümünü kolaylaştırıyoruz.
      Yenilikçi çözümlerimiz sayesinde müşterileriniz menülerinize anında ulaşabilir, fiyat değişiklikleri otomatik güncellenir
      ve e-Devlet API entegrasyonu ile yasal yükümlülüklerinizi zahmetsizce yerine getirirsiniz.
    </p>
  </div>
</section>

<!-- İLETİŞİM -->
<section id="contact" class="contact-section">
  <div class="container">
    <h2 class="text-center mb-5">İletişim</h2>
    <div class="row g-4 text-center">
      <div class="col-md-4">
        <div class="contact-box">
          <i class="bi bi-envelope-fill text-primary fs-1 mb-3"></i>
          <h5 class="fw-semibold">E-Posta</h5>
          <p><a href="mailto:info@vovmenu.com" class="text-decoration-none">info@vovmenu.com</a></p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="contact-box">
          <i class="bi bi-telephone-fill text-primary fs-1 mb-3"></i>
          <h5 class="fw-semibold">Telefon</h5>
          <p><a href="tel:+902123456789" class="text-decoration-none">+90 (212) 345 67 89</a></p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="contact-box">
          <i class="bi bi-geo-alt-fill text-primary fs-1 mb-3"></i>
          <h5 class="fw-semibold">Adres</h5>
          <p>İstanbul, Türkiye</p>
        </div>
      </div>
    </div>
  </div>
</section>



<!-- FOOTER -->
<footer>
  <div class="mb-2">
    <i class="bi bi-facebook"></i>
    <i class="bi bi-instagram"></i>
    <i class="bi bi-twitter"></i>
  </div>
  &copy; <?= date('Y') ?> VovMenu — Profesyonel Dijital Menü Sistemi
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

// Navbar aktif link vurgulama
const sections = document.querySelectorAll("section[id]");
const navLinks = document.querySelectorAll(".navbar .nav-link");

window.addEventListener("scroll", () => {
  let current = "";

  sections.forEach(section => {
    const sectionTop = section.offsetTop - 120; // navbar yüksekliği kadar offset
    const sectionHeight = section.clientHeight;
    if (pageYOffset >= sectionTop && pageYOffset < sectionTop + sectionHeight) {
      current = section.getAttribute("id");
    }
  });

  navLinks.forEach(link => {
    link.classList.remove("active");
    if (link.getAttribute("href") === "#" + current) {
      link.classList.add("active");
    }
  });
});    
// Popup
window.addEventListener('load', () => {
  const modal = new bootstrap.Modal(document.getElementById('lawModal'));
  setTimeout(() => modal.show(), 1500);
});

// Sayaç animasyonu görünür olduğunda başlasın
const counters = document.querySelectorAll('.count');
let started = false;

function animateCounters() {
  const speed = 80;
  counters.forEach(counter => {
    const updateCount = () => {
      const target = +counter.getAttribute('data-target');
      const count = +counter.innerText;
      const inc = target / 60;
      if (count < target) {
        counter.innerText = Math.ceil(count + inc);
        setTimeout(updateCount, speed);
      } else {
        counter.innerText = target.toLocaleString('tr-TR');
      }
    };
    updateCount();
  });
}

// Sayfada istatistik bölümü görünür olunca animasyonu başlat
const statsSection = document.querySelector('.stats-section');
const observer = new IntersectionObserver(entries => {
  if (entries[0].isIntersecting && !started) {
    started = true;
    animateCounters();
  }
}, { threshold: 0.5 });

if (statsSection) observer.observe(statsSection);
</script>

</body>
</html>
