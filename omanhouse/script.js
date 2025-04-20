document.addEventListener("DOMContentLoaded", function() {
    let inputs = document.querySelectorAll("input[type='number']");
    inputs.forEach(input => {
        input.addEventListener("input", function() {
            this.value = this.value.replace(/[^\d.]/g, '');
        });
    });
});
