document.addEventListener("DOMContentLoaded", () => {
  const fadeElements = document.querySelectorAll(".fade-in");

  const observer = new IntersectionObserver(
    (entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target); // animate once
        }
      });
    },
    {
      threshold: 0.15
    }
  );

  fadeElements.forEach(el => observer.observe(el));
});

document.addEventListener("DOMContentLoaded", function() {
        
        const track = document.getElementById("sliderTrack");
        const cards = document.querySelectorAll(".testimonial-card");
        const prevBtn = document.getElementById("prevBtn");
        const nextBtn = document.getElementById("nextBtn");
        const dotsContainer = document.getElementById("sliderDots");
        const slider = document.querySelector(".testimonial-slider");

        // Clone all cards for infinite loop
        cards.forEach(card => {
            const clone = card.cloneNode(true);
            track.appendChild(clone);
        });

        // Also prepend clones for backward scrolling
        const originalCards = Array.from(cards);
        originalCards.reverse().forEach(card => {
            const clone = card.cloneNode(true);
            track.prepend(clone);
        });

        // Now track includes original + cloned cards
        const allCards = document.querySelectorAll(".testimonial-card");
        
        let index = originalCards.length; // Start at first real slide
        const cardWidth = cards[0].offsetWidth + 30; // Gap included
        let autoSlide;

        // Create dots (only for original cards)
        originalCards.forEach((_, i) => {
            const dot = document.createElement("span");
            if (i === 0) dot.classList.add("active");
            dot.addEventListener("click", () => {
                index = i + originalCards.length;
                moveSlider();
                restartAutoSlide();
            });
            dotsContainer.appendChild(dot);
        });

        const dots = document.querySelectorAll(".slider-dots span");

        function updateDots() {
            const realIndex = index % originalCards.length;
            dots.forEach(dot => dot.classList.remove("active"));
            dots[realIndex].classList.add("active");
        }

        function moveSlider() {
            track.style.transition = "transform 0.5s ease-in-out";
            track.style.transform = `translateX(-${index * cardWidth}px)`;
            updateDots();
        }

        function nextSlide() {
            index++;
            moveSlider();

            // If we reach the cloned end, jump back to real start seamlessly
            track.addEventListener('transitionend', function handler() {
                if (index >= allCards.length - originalCards.length) {
                    index = originalCards.length;
                    track.style.transition = "none";
                    track.style.transform = `translateX(-${index * cardWidth}px)`;
                }
                track.removeEventListener('transitionend', handler);
            });
        }

        function prevSlide() {
            index--;
            moveSlider();

            // If we reach the cloned start, jump to real end seamlessly
            track.addEventListener('transitionend', function handler() {
                if (index < originalCards.length) {
                    index = allCards.length - originalCards.length - 1;
                    track.style.transition = "none";
                    track.style.transform = `translateX(-${index * cardWidth}px)`;
                }
                track.removeEventListener('transitionend', handler);
            });
        }

        nextBtn.addEventListener("click", () => {
            nextSlide();
            restartAutoSlide();
        });

        prevBtn.addEventListener("click", () => {
            prevSlide();
            restartAutoSlide();
        });

        // Set initial position
        track.style.transform = `translateX(-${index * cardWidth}px)`;

        // Auto slide functions
        function startAutoSlide() {
            autoSlide = setInterval(nextSlide, 4000);
        }

        function stopAutoSlide() {
            clearInterval(autoSlide);
        }

        function restartAutoSlide() {
            stopAutoSlide();
            startAutoSlide();
        }

        // Pause on hover
        slider.addEventListener("mouseenter", stopAutoSlide);
        slider.addEventListener("mouseleave", startAutoSlide);

        // Handle window resize
        window.addEventListener("resize", () => {
            const newCardWidth = cards[0].offsetWidth + 30;
            track.style.transform = `translateX(-${index * newCardWidth}px)`;
        });

        startAutoSlide();
    });

    document.addEventListener("DOMContentLoaded", () => {
  const counters = document.querySelectorAll(".counter");
  let hasAnimated = false;

  const formatNumber = (num) => {
    if (num >= 1_000_000) {
      return (num / 1_000_000).toFixed(1).replace(/\.0$/, "") + "M";
    } else if (num >= 1_000) {
      return (num / 1_000).toFixed(1).replace(/\.0$/, "") + "K";
    }
    return num;
  };

  const animateCounters = () => {
    if (hasAnimated) return;
    hasAnimated = true;

    counters.forEach(counter => {
      const target = parseFloat(counter.dataset.target);
      const isDecimal = target % 1 !== 0;
      const duration = 2000;
      const startTime = performance.now();

      const update = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const value = progress * target;

        counter.textContent = isDecimal
          ? value.toFixed(1)
          : formatNumber(Math.floor(value));

        if (progress < 1) {
          requestAnimationFrame(update);
        } else {
          counter.textContent = isDecimal
            ? target.toFixed(1)
            : formatNumber(target);
        }
      };

      requestAnimationFrame(update);
    });
  };

  const observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) {
        animateCounters();
        observer.disconnect();
      }
    },
    { threshold: 0.4 }
  );

  observer.observe(document.querySelector(".stats-section"));
});