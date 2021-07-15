document.addEventListener('DOMContentLoaded', () => {
  const ratios = document.getElementById('media-attachment-ratio-filters');
  const typeFilter = document.getElementById('attachment-filter');
  let originalTypeFilter = 0;

  /**
   * manageTypeFilterState
   *
   * Used as a change handle for our ratios object as well as called when the
   * DOM content is loaded, this method disables the attachment type filter
   * when an aspect ratio is chosen.  This is to avoid someone trying to search
   * for an audio clip with an aspect ratio of 16:9 which doesn't make any
   * sense.  One day, perhaps searching for video clips with specific
   * proportions might be a thing, but that day is not this day.
   *
   * @return void
   */
  function manageTypeFilterState() {
    if (ratios.options[ratios.selectedIndex].value !== 'all') {

      // in case someone has changed the type filter, we'll remember what it
      // was.  that way, we can replace that filter's state as needed in the
      // else block.  because the originalTypeFilter variable is declared
      // outside the scope of this block, it's value will be maintained across
      // multiple calls to this method.

      originalTypeFilter = typeFilter.selectedIndex;
      typeFilter.selectedIndex = 1;
      typeFilter.disabled = true;
    } else {
      typeFilter.selectedIndex = originalTypeFilter;
      typeFilter.disabled = false;
    }
  }

  // now we attach that method to our ratios as its change handler but we also
  // call it immediately.  that way, if the page loads with a chosen aspect
  // ratio, we the state of the type filter will be set correctly.

  ratios.addEventListener('change', manageTypeFilterState);
  manageTypeFilterState();
});
