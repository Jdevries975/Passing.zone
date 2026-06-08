/* 2025-12-07 Claude und Juli: Archive-Detail laden, wenn Button geklickt*/
// // Warten bis das DOM geladen ist
document.addEventListener('DOMContentLoaded', function() {
  // Button auswählen
  const button = document.querySelector('.details_button');
  
  // Prüfen ob Button existiert
  if (button) {
    // Click-Event hinzufügen
    button.addEventListener('click', function() {
      // Alle Elemente mit der Klasse "archive-extras" auswählen
      const archiveExtras = document.querySelectorAll('.archive-extras');
      
      // Durch alle Elemente iterieren und ein-/ausblenden
      archiveExtras.forEach(function(element) {
        // Aktuellen computed style prüfen
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

document.onreadystatechange = function () {
	/*if (document.readyState == "interactive") {
		// Add more filters button to dom
		
		// Get the two div elements
		const typeDiv = document.querySelector('.wpc-filter-pattern-type');
		const tagDiv = document.querySelector('.wpc-filter-pattern-tag');

		//create "more filters" button
		let moreButton= document.createElement("span");
		moreButton.innerText = "more filters"
		moreButton.setAttribute("id", "more_filters");
		//moreButton.classList += "open"

		// Ensure both elements exist
		if (typeDiv && tagDiv) {
			// Create the wrapper div
			const moreFiltersWrapper = document.createElement('div');
			moreFiltersWrapper.id = 'wpc-filter-wrapper'; 
			// add more filters button to wrapper
			moreFiltersWrapper.appendChild(moreButton)

			const dropdownContent = document.createElement('div');
			dropdownContent.classList += 'dropdown-content'
			dropdownContent.style.transition = 'max-height 0.3s ease-out, opacity 0.1s ease-out';
    		dropdownContent.style.maxHeight = '0';
    		dropdownContent.style.opacity = '0';

			// Insert the wrapper before the first div
			typeDiv.parentNode.insertBefore(moreFiltersWrapper, typeDiv);

			// Move both divs into the wrapper
			dropdownContent.appendChild(typeDiv);
			dropdownContent.appendChild(tagDiv);

			moreFiltersWrapper.appendChild(dropdownContent);
		}

		document.querySelectorAll('#more_filters').forEach(trigger => {
			trigger.addEventListener('click', function () {
				const dropdownContent = this.parentElement.querySelector('.dropdown-content');

				if (this.classList.contains('open')) {
					// Hide with animation
					dropdownContent.style.transition = 'max-height 0.3s ease-out, opacity 0.1s ease-out';
					dropdownContent.style.maxHeight = '0';
					dropdownContent.style.opacity = '0';
				} else {
					// Show with animation
					dropdownContent.style.transition = 'max-height 0.3s ease-in, opacity 0.3s ease-in';
					dropdownContent.style.maxHeight = dropdownContent.scrollHeight + 500 + 'px';
					dropdownContent.style.opacity = '1';
				}

				this.classList.toggle('open');
			});
		});
	}*/
	/* jj original
     if (document.readyState == "interactive") {
       // document is ready. Load your javascript here.
		var lineBreak = document.createElement('br')
		var span = document.createElement("span")
		var wrapper =  document.querySelector('.entry-content .wpc-filter-content.wpc-filter-pattern-difficulty')
		 if (wrapper) {
			levelText = wrapper.getElementsByTagName("a")[0]
			originalText = levelText.text.split(" ")[1]
			levelText.text = ""
			span.innerHTML  = originalText
			span.classList.add("pz-difficulty-text")
			levelText.appendChild(lineBreak)
			levelText.appendChild(span)
		 }
	 }
/* 2025-11-30 jdev & Claude*/
function modifyDifficultyDisplay() {
    const wrappers = document.querySelectorAll('.pz-difficulty-wrapper');
    
    wrappers.forEach(wrapper => {
        const levelText = wrapper.getElementsByTagName("a")[0];
        if (levelText) {
            const originalText = levelText.textContent.split(" ")[1];
            
            // Prüfen, ob der Text bereits bearbeitet wurde
            if (!originalText || originalText === "undefined") {
                return;
            }
            
            levelText.textContent = "";
            
            const lineBreak = document.createElement('br');
            const span = document.createElement("span");
            span.innerHTML = originalText;
            span.classList.add("pz-difficulty-text");
            
            levelText.appendChild(lineBreak);
            levelText.appendChild(span);
            wrapper.style.visibility = "visible";
        }
    });
}

// Run when document is ready
if (document.readyState === "complete") {
    modifyDifficultyDisplay();
} else {
    document.addEventListener("DOMContentLoaded", modifyDifficultyDisplay);
}
}


/*Slider magic*/
document.addEventListener("DOMContentLoaded", (event) => {
	buildSlider();
});

// Configuration
const VISIBLE_ITEMS = 3;
let currentIndex = 0;

function buildSlider() {  
  // Get the filter container
  const filterSection = document.querySelector('.wpc-filter-number-of-jugglers');
  const filterList = filterSection.querySelector('.wpc-filters-ul-list');
  const items = Array.from(filterList.querySelectorAll('.wpc-radio-item'));
  document.querySelector('.wpc-filter-content.wpc-filter-number-of-jugglers').style.cssText = "display: flex";
	
  // Wrap the list in a slider container
  const sliderWrapper = document.createElement('div');
  sliderWrapper.className = 'slider-wrapper';
  sliderWrapper.style.cssText = 'overflow: hidden; padding: 0; width: 80vw';
  
  const sliderTrack = document.createElement('div');
  sliderTrack.className = 'slider-track';
  sliderTrack.style.cssText = 'display: flex;';
  
  // Move items into slider track
  filterList.parentNode.insertBefore(sliderWrapper, filterList);
  sliderWrapper.appendChild(sliderTrack);
  sliderTrack.appendChild(filterList);
  
  // Style the list and items
  filterList.style.cssText = 'display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; overflow: unset; flex-wrap: nowrap; width: 100%;';
  items.forEach(item => {
    item.style.cssText = 'flex: 0 0 calc(33.333% - 7px); min-width: 0;';
  });
  
  filterList.children[filterList.children.length-1].style.cssText = 'flex: 0 0 calc(37.333% - 7px); min-width: 0;'
  
  
  // Create navigation buttons
  const prevBtn = document.createElement('button');
  const prevBtnSpan = document.createElement('span');
  prevBtnSpan.classList += 'wpc-open-icon arrow-left';
  prevBtn.className = 'slider-nav slider-prev';
  // prevBtn.textContent = '‹';
  prevBtn.appendChild(prevBtnSpan)
  
  const nextBtn = document.createElement('button');
  const nextBtnSpan = document.createElement('span');
  nextBtnSpan.classList += 'wpc-open-icon arrow-right';
  nextBtn.className = 'slider-nav slider-next';
  nextBtn.style.left = 'auto';
  nextBtn.style.right = '-2px';
  nextBtn.appendChild(nextBtnSpan)
  
  sliderWrapper.appendChild(prevBtn);
  sliderWrapper.appendChild(nextBtn);

  // Initialize
  updateSlider(sliderTrack, prevBtn, nextBtn, items, currentIndex);

   // Navigation handlers
  prevBtn.addEventListener('click', () => {
    if (currentIndex > 0) {
      currentIndex--;
	  sliderTrack.style.cssText = 'display: flex; transition: transform 0.3s ease;';
      updateSlider(sliderTrack, prevBtn, nextBtn, items, currentIndex);
	  sliderTrack.style.transition = '0.3s ease';
    }
  });

  nextBtn.addEventListener('click', () => {
    if (currentIndex < items.length - VISIBLE_ITEMS) {
      currentIndex++;
	  sliderTrack.style.cssText = 'display: flex; transition: transform 0.3s ease;';
      updateSlider(sliderTrack, prevBtn, nextBtn, items, currentIndex);
	  sliderTrack.style.transition = '0.3s ease';
    }
  });

}

// Update slider position
function updateSlider(sliderTrack, prevBtn, nextBtn, items) {
  const itemWidth = items[0].offsetWidth;
  const gap = 10;
  const offset = -(currentIndex * (itemWidth + gap));
  sliderTrack.style.transform = `translateX(${offset}px)`;
  
  // Update button states
  prevBtn.disabled = currentIndex === 0;
  nextBtn.disabled = currentIndex >= items.length - VISIBLE_ITEMS;
  
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
  //prevBtn.style.backgroundColor  = prevBtn.disabled ? '#33225D': '#33225D';
  nextBtn.style.opacity = nextBtn.disabled ? '0.3' : '1';
  nextBtn.style.cursor = nextBtn.disabled ? 'not-allowed' : 'pointer';
  //nextBtn.style.backgroundColor  = nextBtn.disabled ? '#33225D': '#33225D'
}

// Handle window resize
let resizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => updateSlider(sliderTrack, prevBtn, nextBtn, items), 100);
});


jQuery(document).on("ajaxComplete", function(){
   buildSlider();
 });
