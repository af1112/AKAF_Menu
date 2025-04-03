AOS.init();

function updateCartCount() {
    fetch('get_cart_count.php')
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (typeof data.count === 'undefined') {
            console.error('Invalid response from server:', data);
            return;
        }
        const cartCountElement = document.querySelector('.cart-count');
        if (data.count > 0) {
            if (cartCountElement) {
                cartCountElement.textContent = data.count;
            } else {
                const cartLink = document.querySelector('a[href="cart.php"]');
                if (cartLink) {
                    cartLink.innerHTML += `<span class="cart-count">${data.count}</span>`;
                }
            }
        } else if (cartCountElement) {
            cartCountElement.remove();
        }
    })
    .catch(error => console.error('Error:', error));
}

function addToCart(foodId) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'food_id=' + encodeURIComponent(foodId) + '&quantity=1'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(langMessages.addedToCart);
            updateCartCount();
        } else {
            alert(data.message || langMessages.failedToAddToCart);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert(langMessages.failedToAddToCart);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
});

// Image Zoom Functionality
document.querySelectorAll('.zoomable-image').forEach(image => {
    image.addEventListener('click', function() {
        const fullImage = this.getAttribute('data-full-image');
        const modalImage = document.getElementById('zoomedImage');
        modalImage.src = fullImage;
        const modal = new bootstrap.Modal(document.getElementById('imageZoomModal'));
        modal.show();
    });
});

// Category Carousel Drag Functionality
const categoryCarousel = document.querySelector('.category-items');
if (categoryCarousel) {
    let isDown = false;
    let startX;
    let scrollLeft;

    categoryCarousel.addEventListener('mousedown', (e) => {
        isDown = true;
        categoryCarousel.classList.add('active');
        startX = e.pageX - categoryCarousel.offsetLeft;
        scrollLeft = categoryCarousel.scrollLeft;
    });

    categoryCarousel.addEventListener('mouseleave', () => {
        isDown = false;
        categoryCarousel.classList.remove('active');
    });

    categoryCarousel.addEventListener('mouseup', () => {
        isDown = false;
        categoryCarousel.classList.remove('active');
    });

    categoryCarousel.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - categoryCarousel.offsetLeft;
        const walk = (x - startX) * 2;
        categoryCarousel.scrollLeft = scrollLeft - walk;
    });
}