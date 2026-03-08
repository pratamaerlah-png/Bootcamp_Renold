// Mobile Menu Toggle
const btn = document.getElementById('mobile-menu-btn');
const menu = document.getElementById('mobile-menu');

if (btn && menu) {
    btn.addEventListener('click', () => {
        menu.classList.toggle('hidden');
    });
}

// Event Tabs Logic
function switchTab(category) {
    const tabs = ['lari', 'konser', 'selesai'];
    
    tabs.forEach(tab => {
        const content = document.getElementById(`content-${tab}`);
        const button = document.getElementById(`tab-${tab}`);
        
        if (content && button) {
            if (tab === category) {
                content.classList.remove('hidden');
                button.className = "px-6 py-2 rounded-md text-sm font-bold bg-blue-600 text-white shadow-sm transition-all";
            } else {
                content.classList.add('hidden');
                button.className = "px-6 py-2 rounded-md text-sm font-bold text-gray-400 hover:text-white transition-all";
            }
        }
    });
}

// Scroll Animation Logic
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px" // Trigger sedikit sebelum elemen benar-benar terlihat penuh
    });

    const sectionsToAnimate = document.querySelectorAll('.reveal');
    sectionsToAnimate.forEach(section => {
        observer.observe(section);
    });

    // Number Counter Animation
    const statsSection = document.getElementById('stats-section');
    if (statsSection) {
        const countObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counters = document.querySelectorAll('.counter');
                    counters.forEach(counter => {
                        const target = +counter.getAttribute('data-target');
                        const duration = 2000; // 2 seconds
                        const increment = target / (duration / 16);
                        
                        let current = 0;
                        const updateCount = () => {
                            current += increment;
                            if (current < target) {
                                counter.innerText = Math.ceil(current).toLocaleString('id-ID');
                                requestAnimationFrame(updateCount);
                            } else {
                                counter.innerText = target.toLocaleString('id-ID');
                            }
                        };
                        updateCount();
                    });
                    countObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        countObserver.observe(statsSection);
    }
});

// Parallax Effect for Hero Section
window.addEventListener('scroll', () => {
    const heroBg = document.getElementById('hero-bg');
    if (heroBg) {
        const scrollPosition = window.pageYOffset;
        heroBg.style.transform = `translateY(${scrollPosition * 0.5}px)`;
    }
});

// Navbar Scroll Effect
const navbar = document.getElementById('navbar');
function updateNavbar() {
    if (window.scrollY > 50) {
        navbar.classList.remove('bg-transparent', 'border-transparent');
        navbar.classList.add('bg-black/80', 'backdrop-blur-sm', 'border-white/10');
    } else {
        navbar.classList.add('bg-transparent', 'border-transparent');
        navbar.classList.remove('bg-black/80', 'backdrop-blur-sm', 'border-white/10');
    }
}
if (navbar) {
    window.addEventListener('scroll', updateNavbar);
    updateNavbar(); // Check on load
}

// Loading Screen Logic
window.addEventListener('load', () => {
    const loader = document.getElementById('loading-screen');
    if (loader) {
        setTimeout(() => {
            loader.classList.add('fade-out');
            setTimeout(() => { loader.style.display = 'none'; }, 500);
        }, 1500); // Tahan minimal 1.5 detik agar animasi terlihat
    }
});

// Revenue Calculator Logic
document.addEventListener('DOMContentLoaded', () => {
    const rangeQty = document.getElementById('range-qty');
    const rangePrice = document.getElementById('range-price');
    const displayQty = document.getElementById('display-qty');
    const displayPrice = document.getElementById('display-price');
    const inputFeeOther = document.getElementById('fee-other');
    const inputFeeUs = document.getElementById('fee-us');
    const resultRevenue = document.getElementById('result-revenue');
    const resultSavings = document.getElementById('result-savings');
    const inputDataValue = document.getElementById('data-value');
    const resultValuation = document.getElementById('result-valuation');

    function updateCalculator() {
        if (!rangeQty || !rangePrice) return;

        const qty = parseInt(rangeQty.value) || 0;
        const price = parseInt(rangePrice.value) || 0;
        const feeOtherPercent = parseFloat(inputFeeOther ? inputFeeOther.value : 5) / 100;
        const feeUsPercent = parseFloat(inputFeeUs ? inputFeeUs.value : 2) / 100;
        const dataValuePerUser = parseInt(inputDataValue ? inputDataValue.value : 0) || 0;
        
        const revenue = qty * price;
        
        const costOther = revenue * feeOtherPercent;
        const costUs = revenue * feeUsPercent;
        const savings = costOther - costUs;
        
        const dataValuation = qty * dataValuePerUser;

        // Format Currency
        const formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });

        if(displayQty) displayQty.innerText = qty.toLocaleString('id-ID');
        if(displayPrice) displayPrice.innerText = price.toLocaleString('id-ID');
        if(resultRevenue) resultRevenue.innerText = formatter.format(revenue);
        if(resultSavings) resultSavings.innerText = formatter.format(savings);
        if(resultValuation) resultValuation.innerText = formatter.format(dataValuation);
    }

    if (rangeQty && rangePrice) {
        rangeQty.addEventListener('input', updateCalculator);
        rangePrice.addEventListener('input', updateCalculator);
        // Tambahkan event 'change' untuk kompatibilitas lebih baik
        rangeQty.addEventListener('change', updateCalculator);
        rangePrice.addEventListener('change', updateCalculator);

        if(inputFeeOther) {
            inputFeeOther.addEventListener('input', updateCalculator);
            inputFeeOther.addEventListener('change', updateCalculator);
        }
        if(inputFeeUs) {
            inputFeeUs.addEventListener('input', updateCalculator);
            inputFeeUs.addEventListener('change', updateCalculator);
        }
        if(inputDataValue) {
            inputDataValue.addEventListener('input', updateCalculator);
            inputDataValue.addEventListener('change', updateCalculator);
        }
        // Initialize
        updateCalculator();
    }
});

// Testimonial Slider Logic
let currentTestiIndex = 0;
const testimonialSlides = document.querySelectorAll('.testimonial-slide');
const testimonialDots = document.querySelectorAll('#testimonial-slider + div button');
let testimonialInterval;

function showTestimonial(index) {
    if (!testimonialSlides.length) return;
    
    // Hide all
    testimonialSlides.forEach(slide => {
        slide.classList.remove('opacity-100', 'z-10');
        slide.classList.add('opacity-0', 'z-0');
    });
    testimonialDots.forEach(dot => {
        dot.classList.remove('bg-white', 'w-6');
        dot.classList.add('bg-white/40');
    });

    // Show active
    testimonialSlides[index].classList.remove('opacity-0', 'z-0');
    testimonialSlides[index].classList.add('opacity-100', 'z-10');
    if(testimonialDots[index]) {
        testimonialDots[index].classList.remove('bg-white/40');
        testimonialDots[index].classList.add('bg-white', 'w-6');
    }
    currentTestiIndex = index;
}

function nextTestimonial() {
    let nextIndex = (currentTestiIndex + 1) % testimonialSlides.length;
    showTestimonial(nextIndex);
    resetTestimonialTimer();
}

function prevTestimonial() {
    let prevIndex = (currentTestiIndex - 1 + testimonialSlides.length) % testimonialSlides.length;
    showTestimonial(prevIndex);
    resetTestimonialTimer();
}

function resetTestimonialTimer() {
    clearInterval(testimonialInterval);
    testimonialInterval = setInterval(nextTestimonial, 5000);
}

// Auto slide every 5 seconds
if (testimonialSlides.length > 1) {
    resetTestimonialTimer();
}