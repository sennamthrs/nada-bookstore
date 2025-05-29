// Kode JavaScript yang disempurnakan untuk menangani tampilan mobile header
document.addEventListener('DOMContentLoaded', function() {
  // Ambil elemen-elemen yang dibutuhkan
  const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
  const navLinks = document.querySelector('.nav-links');
  const body = document.body;
  
  // Tambahkan property display secara default jika belum ada
  if (window.innerWidth <= 992) {
    navLinks.style.display = 'none';
  }
  
  // Toggle menu saat tombol diklik
  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      if (navLinks.style.display === 'none' || navLinks.style.display === '') {
        navLinks.style.display = 'flex';
        navLinks.classList.add('menu-open');
        mobileMenuBtn.classList.add('active');
        body.classList.add('menu-active');
      } else {
        navLinks.style.display = 'none';
        navLinks.classList.remove('menu-open');
        mobileMenuBtn.classList.remove('active');
        body.classList.remove('menu-active');
      }
    });
  }
  
  // Tutup menu saat mengklik di luar menu
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 992) {
      if (!navLinks.contains(e.target) && 
          !mobileMenuBtn.contains(e.target) && 
          navLinks.style.display === 'flex') {
        navLinks.style.display = 'none';
        navLinks.classList.remove('menu-open');
        mobileMenuBtn.classList.remove('active');
        body.classList.remove('menu-active');
      }
    }
  });
  
  // Tangani perubahan ukuran layar
  window.addEventListener('resize', function() {
    if (window.innerWidth > 992) {
      // Desktop view
      navLinks.style.display = 'flex';
      navLinks.classList.remove('menu-open');
      mobileMenuBtn.classList.remove('active');
      body.classList.remove('menu-active');
    } else {
      // Mobile view - hide menu by default if not already open
      if (!navLinks.classList.contains('menu-open')) {
        navLinks.style.display = 'none';
      }
    }
  });
  
  // Tangani dropdown user (jika ada)
  const userEmailBtn = document.querySelector('.user-email-btn');
  const userDropdown = document.querySelector('.user-dropdown');
  
  if (userEmailBtn && userDropdown) {
    userEmailBtn.addEventListener('click', function(e) {
      e.stopPropagation(); // Mencegah event click menyebar ke document
      userDropdown.classList.toggle('show');
    });
    
    // Tutup dropdown saat klik di luar
    document.addEventListener('click', function(e) {
      if (!userEmailBtn.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.classList.remove('show');
      }
    });
  }
});