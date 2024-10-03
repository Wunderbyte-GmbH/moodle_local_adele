let activeTooltip = null;
let showTimeout = null;

export default {
  mounted(el, binding) {
    let tooltip = null;

    // Create the tooltip on demand
    const createTooltip = () => {
      if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.textContent = binding.value;

        // Apply the scoped styles to the tooltip
        Object.assign(tooltip.style, {
          backgroundColor: 'black',
          color: 'white',
          padding: '5px',
          borderRadius: '4px',
          fontSize: '12px',
          maxWidth: '150px',
          whiteSpace: 'normal',
          overflowWrap: 'break-word',
          position: 'absolute',
          zIndex: 999,
          pointerEvents: 'none',
          transition: 'visibility 0.2s, opacity 0.2s',
          opacity: 0,
          visibility: 'hidden',
          textAlign: 'center',
        });

        document.body.appendChild(tooltip);
      }
    };

    // Position the tooltip at the center of the hovered element
    const showTooltip = (event) => {
      // Clear any previous timeout
      clearTimeout(showTimeout);

      // Debounce the tooltip display by 200ms
      showTimeout = setTimeout(() => {
        // Hide the active tooltip if there's one
        if (activeTooltip && activeTooltip !== tooltip) {
          activeTooltip.style.opacity = 0;
          activeTooltip.style.visibility = 'hidden';
        }

        // Create and show the current tooltip
        createTooltip();
        const { top, left, width, height } = el.getBoundingClientRect();

        requestAnimationFrame(() => {
          tooltip.style.top = `${top + height + 5}px`; // Adjust 5px below the element
          tooltip.style.left = `${left + width / 2 - tooltip.offsetWidth / 2}px`;
          tooltip.style.opacity = 1;
          tooltip.style.visibility = 'visible';
        });

        // Set the current tooltip as the active one
        activeTooltip = tooltip;
      }, 200); // 200ms delay
    };

    // Hide the tooltip when the mouse leaves
    const hideTooltip = () => {
      clearTimeout(showTimeout); // Clear the timeout in case of fast mouse movement

      if (tooltip) {
        tooltip.style.opacity = 0;
        tooltip.style.visibility = 'hidden';
      }

      if (activeTooltip === tooltip) {
        activeTooltip = null;
      }
    };

    // Lazy-load the tooltip: Only show on hover
    el.addEventListener('mouseenter', showTooltip);
    el.addEventListener('mouseleave', hideTooltip);

    // Add scroll event listener to hide tooltip on fast scroll
    const hideOnScroll = () => {
      if (activeTooltip) {
        hideTooltip();
      }
    };

    // Attach the scroll event listener
    window.addEventListener('scroll', hideOnScroll, true);

    // Clean up when unmounted
    el.cleanupTooltip = () => {
      if (tooltip) {
        tooltip.remove();
        tooltip = null;
      }
      window.removeEventListener('scroll', hideOnScroll, true);
    };
  },

  updated(el, binding) {
    if (binding.value !== binding.oldValue && activeTooltip) {
      activeTooltip.textContent = binding.value;
    }
  },

  unmounted(el) {
    // Remove the tooltip on unmount to prevent memory leaks
    el.cleanupTooltip();
  }
};
