/**
 * createSelect
 *
 * Creates a <select> element, gives it the appropriate ID, and returns it.
 *
 * @return HTMLElement
 */
function createSelect() {
  const select = document.createElement('select');
  select.id = 'media-attachment-ratio-filters';
  return select;
}

/**
 * createOption
 *
 * Creates an <option> element with the specified text and value and marks it
 * as selected if that value matches the currentValue.
 *
 * @return HTMLElement
 */
function createOption(text, value, currentValue) {
  const option = document.createElement('option');
  option.selected = value === currentValue;
  option.value = value;
  option.text = text;
  return option;
}

/**
 * onSelectChange
 *
 * When our <select> element changes, we want to reload the page.  The WP
 * Core grid view controls dont seem to let us hook an AJAX request into the
 * methods that re-paint the grid view; we can get the data that way, but
 * we can't get that data on-screen, as far as I can tell.  So, instead, we
 * just reload.
 *
 * @return void
 */
function onSelectChange(event) {

  // technically, because we don't use AJAX here, we don't need the AJAX
  // action that would typically be necessary.  but, just in case we do find
  // a way to switch from a HTTP refresh to an AJAX call, we'll leave it
  // here so we don't need to remember to add it.

  let data = 'action=query-attachments';
  event.target.parentNode.querySelectorAll('select').forEach((select) => {

    // the grid view date filters are enumerated starting from zero to the
    // most distant month in the list.  we're not exactly sure where that
    // enumeration takes place.  so, instead, for the date filters, we use
    // their text (e.g. July 2021) which we can more easily use to create a
    // WP_Query.

    let value = select.id !== 'media-attachment-date-filters'
      ? select.options[select.selectedIndex].value
      : select.options[select.selectedIndex].text

    data += '&' + select.id + '=' + value;
  });

  // notice that we replace any existing search parameters with our new data.
  // thus, we'd find an address like foo.php?bar=baz and the function call
  // takes the "?bar=baz" part and turns it into simply the "?" character to
  // which we append our data constructed above.

  location.href = location.href.replace(location.search, '?') + data;
}

async function domLoaded() {

  // if there is a ratio within the search parameters on our URL, then we want
  // to extract it into the local scope here.  as is the case with a lot of JS,
  // if the ratio is not present, currentValue will be null, but that's good
  // enough for our work here.

  const params = (new URL(window.location)).searchParams;
  const currentValue = params.get('media-attachment-ratio-filters');

  const select = createSelect();
  select.appendChild(createOption('All aspect ratios', 'all', currentValue));
  select.addEventListener('change', onSelectChange);

  // WordPress has added an inline script prior to this one that includes the
  // aspectRatios variable that we access herein.  therefore, we can use it to
  // construct a <select> element that we then cram into the DOM.  IDEs may not
  // know this variable exists; that's okay, it definitely does.  to help make
  // Dash's IDE stop complaining about unresolvable variables, we set it to a
  // local variable and then use that one so that we they can suppress the
  // warning.

  // noinspection JSUnresolvedVariable
  const ratios = mbarAspectRatios;

  for (let ratio in ratios) {
    if (ratios.hasOwnProperty(ratio)) {
      select.appendChild(createOption(ratios[ratio], ratio, currentValue));
    }
  }

  // the insertionPoint for our select is not available when the DOM content is
  // loaded.  so, we have to wait for it.  in case that changes someday, we'll
  // see if it's available.  then, if it's not, we start a loop and wait 50ms
  // before checking again.  we'll loop until we find it and then we can
  // proceed.

  let insertionPoint = document.getElementById('media-attachment-date-filters');

  while (!insertionPoint) {

    // noop refers to "no operation," an old Assembly term for a moment at
    // which the computer does nothing.  so, this Promise does nothing other
    // that force us to wait for the 50ms timeout because we specify that we
    // have to wait for it to resolve before continuing.

    await new Promise(noop => setTimeout(noop, 50));
    insertionPoint = document.getElementById('media-attachment-date-filters');
  }

  // JS has an insertBefore method but not an insertAfter.  sadly, the element
  // after our insertion point lacks an ID making it harder to find.  so, we
  // find the element before our insertion point and insert before its next
  // sibling which is the same as inserting after the element we've found.

  insertionPoint.parentNode.insertBefore(select, insertionPoint.nextSibling);

  // finally, if our current ratio value on the query string isn't all, we
  // want to disable the attachment type filter.  this is to prevent someone
  // from trying to search for audio clips that have an aspect ratio of 1:1,
  // for example.  perhaps searching for video clips of a certain proportion
  // might be a thing one day, but that day is not this day.  we don't have
  // to worry about disabling it because the page refresh when our ratio is
  // changed will handle that for us.

  if (currentValue && currentValue !== 'all') {
    const typeFilter = document.getElementById('media-attachment-filters');
    typeFilter.selectedIndex = 1;
    typeFilter.disabled = true;
  }
}

document.addEventListener('DOMContentLoaded', domLoaded);
