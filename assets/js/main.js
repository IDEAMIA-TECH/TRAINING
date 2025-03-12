document.addEventListener('DOMContentLoaded', function() {
    // Banner rotatorio
    const bannerContainer = document.querySelector('.banner-container');
    const slides = document.querySelectorAll('.banner-slide');
    const prevButton = document.querySelector('.banner-prev');
    const nextButton = document.querySelector('.banner-next');
    let currentSlide = 0;

    function updateSlidePosition() {
        bannerContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        updateSlidePosition();
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
        updateSlidePosition();
    }

    // Event listeners
    prevButton.addEventListener('click', prevSlide);
    nextButton.addEventListener('click', nextSlide);

    // Auto-rotate cada 5 segundos
    setInterval(nextSlide, 5000);
}); 