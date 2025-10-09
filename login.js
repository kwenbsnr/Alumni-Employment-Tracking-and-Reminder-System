document.addEventListener("DOMContentLoaded", () => {
  const roleSelectors = document.querySelectorAll(".role-selector");
  const roleInput = document.getElementById("selectedRole");

  roleSelectors.forEach(selector => {
    selector.addEventListener("click", () => {
      // Remove "selected" from others
      roleSelectors.forEach(s => s.classList.remove("selected"));

      // Add "selected" to clicked role
      selector.classList.add("selected");

      // Store selected role in hidden input
      roleInput.value = selector.dataset.role;
      console.log("Selected Role:", roleInput.value); // for testing
    });
  });
});
