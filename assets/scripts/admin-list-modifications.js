document.addEventListener('DOMContentLoaded', () => {
  const ratios = document.getElementById('media-attachment-ratio-filters');
  const typeFilter = document.getElementById('attachment-filter');
  let originalTypeFilter = 0;

  const changeHandler = () => {
    if (ratios.options[ratios.selectedIndex].value !== 'all') {
      originalTypeFilter = typeFilter.selectedIndex;
      typeFilter.selectedIndex = 1;
      typeFilter.disabled = true;
    } else {
      typeFilter.selectedIndex = originalTypeFilter;
      typeFilter.disabled = false;
    }
  }

  changeHandler();
  ratios.addEventListener('change', changeHandler);
});
