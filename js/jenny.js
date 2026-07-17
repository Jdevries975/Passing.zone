(function () {
  // Toggle "archive extras" rows when the details button is clicked
  document.addEventListener('DOMContentLoaded', function() {
    const button = document.querySelector('.details_button');
    if (button) {
      button.addEventListener('click', function() {
        const archiveExtras = document.querySelectorAll('.archive-extras');
        archiveExtras.forEach(function(element) {
          const currentDisplay = window.getComputedStyle(element).display;
          if (currentDisplay === 'none') {
            element.style.display = 'block';
          } else {
            element.style.display = 'none';
          }
        });
      });
    }
  });

  // WP taxonomy term names include a sort-order prefix (e.g. "4 Expert").
  // Strip it so only the label is shown. Handles two contexts:
  //   .pz-difficulty-wrapper  — the star-widget on single pattern pages
  //   a.pp-post-meta-term     — PowerPack post-meta links in the header
  // Juggler terms are bare numbers ("3", "4" …) so the regex won't match them.
  function modifyDifficultyDisplay() {
      document.querySelectorAll('.pz-difficulty-wrapper').forEach(wrapper => {
          if (wrapper.dataset.pzDone) return;
          const levelText = wrapper.getElementsByTagName("a")[0];
          if (!levelText) return;

          const originalText = levelText.textContent.replace(/^\d+\s*/, '').trim();
          if (!originalText) return;

          levelText.textContent = "";
          const lineBreak = document.createElement('br');
          const span = document.createElement("span");
          span.innerHTML = originalText;
          span.classList.add("pz-difficulty-text");
          levelText.appendChild(lineBreak);
          levelText.appendChild(span);
          wrapper.style.visibility = "visible";
          wrapper.dataset.pzDone = '1';
      });

      document.querySelectorAll('a.pp-post-meta-term').forEach(link => {
          if (link.dataset.pzDone) return;
          if (!link.href.includes('pattern-difficulty')) return;
          link.textContent = link.textContent.replace(/^\d+\s*/, '').trim();
          link.dataset.pzDone = '1';
      });
  }

  document.addEventListener("DOMContentLoaded", modifyDifficultyDisplay);
  window.addEventListener("load", modifyDifficultyDisplay);

  // Catch elements injected after page load (lazy grids, AJAX)
  document.addEventListener("DOMContentLoaded", function() {
      if (window.MutationObserver) {
          new MutationObserver(modifyDifficultyDisplay)
              .observe(document.body, { childList: true, subtree: true });
      }
  });


  const VISIBLE_ITEMS = 3;
  let currentIndex = 0;

  // Elements from the most recent buildSlider() call, shared with updateSlider()
  let sliderTrack, prevBtn, nextBtn, sliderItems;

  // The jugglers filter is a long list — wrap it in a 3-item sliding carousel
  document.addEventListener("DOMContentLoaded", () => {
      buildSlider();
  });

  function buildSlider() {
    const filterSection = document.querySelector('.wpc-filter-number-of-jugglers');
    const filterContent = document.querySelector('.wpc-filter-content.wpc-filter-number-of-jugglers');
    if (!filterSection || !filterContent) return;

    const filterList = filterSection.querySelector('.wpc-filters-ul-list');
    if (!filterList) return;

    const items = Array.from(filterList.querySelectorAll('.wpc-radio-item'));
    if (items.length === 0) return;

    filterContent.style.cssText = "display: flex";

    const sliderWrapper = document.createElement('div');
    sliderWrapper.className = 'slider-wrapper';
    sliderWrapper.style.cssText = 'overflow: hidden; padding: 0; width: 80vw';

    const track = document.createElement('div');
    track.className = 'slider-track';
    track.style.cssText = 'display: flex;';

    filterList.parentNode.insertBefore(sliderWrapper, filterList);
    sliderWrapper.appendChild(track);
    track.appendChild(filterList);

    filterList.style.cssText = 'display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; overflow: unset; flex-wrap: nowrap; width: 100%;';
    items.forEach(item => {
      item.style.cssText = 'flex: 0 0 calc(33.333% - 7px); min-width: 0;';
    });

    // Last item gets slightly more width to account for missing right gap
    filterList.children[filterList.children.length - 1].style.cssText = 'flex: 0 0 calc(37.333% - 7px); min-width: 0;'

    const prev = document.createElement('button');
    const prevSpan = document.createElement('span');
    prevSpan.classList.add('wpc-open-icon', 'arrow-left');
    prev.className = 'slider-nav slider-prev';
    prev.appendChild(prevSpan);

    const next = document.createElement('button');
    const nextSpan = document.createElement('span');
    nextSpan.classList.add('wpc-open-icon', 'arrow-right');
    next.className = 'slider-nav slider-next';
    next.style.left = 'auto';
    next.style.right = '-2px';
    next.appendChild(nextSpan);

    sliderWrapper.appendChild(prev);
    sliderWrapper.appendChild(next);

    currentIndex = 0;
    sliderTrack = track;
    prevBtn = prev;
    nextBtn = next;
    sliderItems = items;

    updateSlider();

    prev.addEventListener('click', () => {
      if (currentIndex > 0) {
        currentIndex--;
        track.style.cssText = 'display: flex; transition: transform 0.3s ease;';
        updateSlider();
        track.style.transition = '0.3s ease';
      }
    });

    next.addEventListener('click', () => {
      if (currentIndex < items.length - VISIBLE_ITEMS) {
        currentIndex++;
        track.style.cssText = 'display: flex; transition: transform 0.3s ease;';
        updateSlider();
        track.style.transition = '0.3s ease';
      }
    });
  }

  function updateSlider() {
    if (!sliderTrack || !sliderItems || sliderItems.length === 0) return;

    const itemWidth = sliderItems[0].offsetWidth;
    const gap = 10;
    const offset = -(currentIndex * (itemWidth + gap));
    sliderTrack.style.transform = `translateX(${offset}px)`;

    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex >= sliderItems.length - VISIBLE_ITEMS;

    if (prevBtn.disabled) {
      prevBtn.classList.add("disabled");
    } else {
      prevBtn.classList.remove('disabled');
    }

    if (nextBtn.disabled) {
      nextBtn.classList.add("disabled");
    } else {
      nextBtn.classList.remove('disabled');
    }

    prevBtn.style.opacity = prevBtn.disabled ? '0.3' : '1';
    prevBtn.style.cursor = prevBtn.disabled ? 'not-allowed' : 'pointer';
    nextBtn.style.opacity = nextBtn.disabled ? '0.3' : '1';
    nextBtn.style.cursor = nextBtn.disabled ? 'not-allowed' : 'pointer';
  }

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(updateSlider, 100);
  });

  // The filter AJAX replaces the DOM, so rebuild the slider after every response
  jQuery(document).on("ajaxComplete", function() {
     buildSlider();
  });
})();
