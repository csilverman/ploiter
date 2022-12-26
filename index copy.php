<?php

//  TODO
//  - Finish commenting the code
//  - Write iOS shortcut to generate and publish JSON files
//    - Field: 'favorite', boolean; shows a star on ones I liked
//    - Field: 'link'. Links to podcast page, or, in the case of films, the PlusMinus page
//  - Finish styling
//  - Have this write an HTML file, so the PHP doesn't run every time
//  - Put it on GitHub
//  - Add a category for Film
//

/*

To add a category: in the main bookmarks folder, add a subfolder named after the category.

The files in the folder must be JSON files that are single key-value pairings (no complex multi-level elements for now). To display a value in a template, just add the value's key as [[value-name]] in the $item_tmp template.

*/


//  If a category of items needs a different template for some reason - it has fields or something that are different - you can specify an override template here.
//  This might seem overkill, but I'd like this system to be versatile enough that someone could have additional categories: books, games, etc.
$template_overrides = [
  'podcasts' => '  <div class="item">
    <h2>[[title]]</h2>
    <p>[[year-added]]</p>
    {{<p>[[occasionally]]</p>}}
    {{<p>[[rating]]</p>}}
    </div>
'
];

// optional fields: things that, if there isn't a parameter, nothing should show up. Find a way of doing this.
// https://regex101.com/r/d8EoDL/1
// $text = '{{test}} {test1} {{test2}} {test5} {{test3}}';
// preg_match_all("/\{{2}.*?\}{2}/", $text, $matches);
// print_r($matches[0]);



function search_array($arr, $search_term) {
  $iter_count = 0;
  $found_at_index = false;

  foreach ($arr as $value) {
    if (strpos($value, $search_term) !== false) {
      $found_at_index = $iter_count;
    }
    $iter_count++;
  }
  return $found_at_index;
}



function apply_array_to_tmp(
  $array,
  $tmp,
  $extras=false
) {

  $markup = $tmp;

//  first, render the optional ones
//  we do this by getting a list of which items in the template
//  are marked optional
preg_match_all("/\{{2}.*?\}{2}/", $tmp, $optional_tags);

//  For some reason, the results are nested in an unnecessary parent array
$optional_tags = $optional_tags[0];

// so $optional_tags is now an array where each item is a {{XXX}} template item

// print_r($optional_tags);

  foreach ( $array as $key => $value ) {

    $the_key = '[['.$key.']]';

    // see if the current key is an optional one
    // by searching the 'optional_tags' array
    // if it's in there
    if( search_array($optional_tags, $the_key) !== false ) {
//      echo 'YESY';
      $found_at_index = search_array($optional_tags, $the_key);
      $optional_markup = $optional_tags[$found_at_index];

//      echo '<b>'.$optional_markup.'</b>';

      // so now we have something like {{<p>[[occasionally]]</p>}}
      // and we need to replace [[occasionally]] with the key

      $optional_markup_with_value = str_replace( $the_key, $value, $optional_markup );

//      echo '<b>' . $optional_markup_with_value . '</b>';

      $markup = str_replace( $optional_markup, $optional_markup_with_value, $markup );

//      $markup = str_replace( $the_key, $value, $optional_markup );
    }

    // We now need to perform cleanup. That means
    // - removing any optional tags that werne't filled
    // - removing the leftover {{ and }} from the tags tghat were filled

//  so iterate over the optional_tags array

    //  At this point, we've replaced all the optional markup
    //  So proceed to replacing the regular tags

    $tmp_key = '[[' . $key . ']]';
    $markup = str_replace( $tmp_key, $value, $markup );
  }
  return $markup;
}

// Comparison function
// https://www.geeksforgeeks.org/sort-a-multidimensional-array-by-date-element-in-php/
function compare_years($element1, $element2) {
    $datetime1 = strtotime($element1['year-added']);
    $datetime2 = strtotime($element2['year-added']);

    // reverse these for ascending order
    return $datetime2 - $datetime1;
}


/**
 * generate_listing()
 * This function takes a single parameter. It looks for a directory with that name, scans the directory, reads all the files inside, and returns markup.
 * @param  [type] $category               [description]
 * @return [type]           [description]
 */
function generate_listing($category) {
  global $template_overrides;
  $final_markup = '';

  //  This is the template for each item
  $item_tmp = <<<TMP

  <div class="item">
  <h2>[[title]]</h2>
  <p>[[year-added]]</p>
  </div>

  TMP;

  //  Read all the files in the specified subfolder
  $json_files = array_filter(glob(getcwd() . '/' . $category . '/*.json'));

  //  What we're going to do here is read all the files, convert them to arrays, and then insert them into a larger array. The end result will be one giant array that contains all the data from the files in the folder. This is basically assembling a database, so the data can be sorted by each item's year.
  //  So set up the mega-array here.
  $full_array = [];

  //  for each file:
  foreach ($json_files as &$value) {
    //  load file
    $file_contents = file_get_contents( $value );

    //  convert it from JSON to an array
    $file_as_array = json_decode($file_contents, true);

    //  and now add it to the mega-array.
    $full_array[] = $file_as_array;

  }
  unset($value); // break the reference with the last element

  //  Sort the array
  usort($full_array, 'compare_years');

  //  This next part is about displaying a section title for each year. At this point, all the items should be grouped by the year I added them. The loop watches the 'year-added' value; when it changes, we're in a new grouping, so add a year title.

  //  Setting up a counter here to track which item we're currently on.
  //  I need this so I can access the previous item in the mega-array.
  $index = 0;

  $current_year = '';

  //  Iterate over the mega-array
  foreach ($full_array as &$value) {
    $current_year = $value['year-added'];

    //  If we're at the very start of the loop, just display the
    //  year of the first item
    if($index == 0)
      $final_markup .= '<h1>' . $value['year-added'] . '</h1>';

    //  Otherwise, see what the year of the previous item was.
    else {
      $prev_year = $full_array[$index - 1]['year-added'];

      //  if the year of the previous item is not the same as the
      //  year of the current item, we're now in a different year grouping
      //  so display a year heading.
      if ($current_year != $prev_year ) {
        $final_markup .= '<h1>' . $value['year-added'] . '</h1>';
      }
    }

    if( array_key_exists( $category, $template_overrides ) ) {
      $item_tmp = $template_overrides[$category];
    }

    //  add the rendered item to the markup we're assembling
    $final_markup .= apply_array_to_tmp($value, $item_tmp);

    //  and boost the counter
    $index++;
  }
  return $final_markup;
}

//  This generates tabs from an array of strings.
function tabs($tab_array) {
  echo '<div class="tabs js-tabs">';

  // Create the tabs themselves
  echo '<ul role="tablist" class="tabs__tab-list">';

  foreach ($tab_array as &$tab) {
    $tabname = $tab;
$tab_tmp = <<<TMP
<li role="presentation"><a href="#section-$tabname" role="tab" aria-controls="section-$tabname" class="tabs__trigger js-tab-trigger">$tabname</a></li>
TMP;

    echo $tab_tmp;
  }
  echo '</ul>';

  // tab content

  foreach ($tab_array as &$tab) {
    $tabname = $tab;
    $tab_content = generate_listing( $tab );
$tab_tmp = <<<TMP

<section id="section-$tabname" role="tabpanel" class="tabs__panel js-tab-panel">
    <h3>$tabname</h3>
    $tab_content
    </section>

TMP;

    echo $tab_tmp;
  }

  echo '</div>';
}


tabs(['tv', 'podcasts']);

?>

<script>
//  From https://codepen.io/stowball/pen/xVWwWe
function TabWidget (el, selectedIndex) {

    if (!el) {
        return;
    }

    this.el = el;
    this.tabTriggers = this.el.getElementsByClassName('js-tab-trigger');
    this.tabPanels = this.el.getElementsByClassName('js-tab-panel');

    if (this.tabTriggers.length === 0 || this.tabTriggers.length !== this.tabPanels.length) {
        return;
    }

    this._init(selectedIndex);
}

TabWidget.prototype._init = function (selectedIndex) {

    this.tabTriggersLength = this.tabTriggers.length;
    this.selectedTab = 0;
    this.prevSelectedTab = null;
    this.clickListener = this._clickEvent.bind(this);
    this.keydownListener = this._keydownEvent.bind(this);
    this.keys = {
        prev: 37,
        next: 39
    };

    for (var i = 0; i < this.tabTriggersLength; i++) {
        this.tabTriggers[i].index = i;
        this.tabTriggers[i].addEventListener('click', this.clickListener, false);
        this.tabTriggers[i].addEventListener('keydown', this.keydownListener, false);

        if (this.tabTriggers[i].classList.contains('is-selected')) {
            this.selectedTab = i;
        }
    }

    if (!isNaN(selectedIndex)) {
        this.selectedTab = selectedIndex < this.tabTriggersLength ? selectedIndex : this.tabTriggersLength - 1;
    }

    this.selectTab(this.selectedTab);
    this.el.classList.add('is-initialized');
};

TabWidget.prototype._clickEvent = function (e) {

    e.preventDefault();

    if (e.target.index === this.selectedTab) {
        return;
    }

    this.selectTab(e.target.index, true);
};

TabWidget.prototype._keydownEvent = function (e) {

    var targetIndex;

    if (e.keyCode === this.keys.prev || e.keyCode === this.keys.next) {
        e.preventDefault();
    }
    else {
        return;
    }

    if (e.keyCode === this.keys.prev && e.target.index > 0) {
        targetIndex = e.target.index - 1;
    }
    else if (e.keyCode === this.keys.next && e.target.index < this.tabTriggersLength - 1) {
        targetIndex = e.target.index + 1;
    }
    else {
        return;
    }

    this.selectTab(targetIndex, true);
};

TabWidget.prototype._show = function (index, userInvoked) {

    this.tabTriggers[index].classList.add('is-selected');
    this.tabTriggers[index].setAttribute('aria-selected', true);
    this.tabTriggers[index].setAttribute('tabindex', 0);

    this.tabPanels[index].classList.remove('is-hidden');
    this.tabPanels[index].setAttribute('aria-hidden', false);
    this.tabPanels[index].setAttribute('tabindex', 0);

    if (userInvoked) {
        this.tabTriggers[index].focus();
    }
};

TabWidget.prototype._hide = function (index) {

    this.tabTriggers[index].classList.remove('is-selected');
    this.tabTriggers[index].setAttribute('aria-selected', false);
    this.tabTriggers[index].setAttribute('tabindex', -1);

    this.tabPanels[index].classList.add('is-hidden');
    this.tabPanels[index].setAttribute('aria-hidden', true);
    this.tabPanels[index].setAttribute('tabindex', -1);
};

TabWidget.prototype.selectTab = function (index, userInvoked) {

    if (this.prevSelectedTab === null) {
        for (var i = 0; i < this.tabTriggersLength; i++) {
            if (i !== index) {
                this._hide(i);
            }
        }
    }
    else {
        this._hide(this.selectedTab);
    }

    this.prevSelectedTab = this.selectedTab;
    this.selectedTab = index;

    this._show(this.selectedTab, userInvoked);
};

TabWidget.prototype.destroy = function () {

    for (var i = 0; i < this.tabTriggersLength; i++) {
        this.tabTriggers[i].classList.remove('is-selected');
        this.tabTriggers[i].removeAttribute('aria-selected');
        this.tabTriggers[i].removeAttribute('tabindex');

        this.tabPanels[i].classList.remove('is-hidden');
        this.tabPanels[i].removeAttribute('aria-hidden');
        this.tabPanels[i].removeAttribute('tabindex');

        this.tabTriggers[i].removeEventListener('click', this.clickListener, false);
        this.tabTriggers[i].removeEventListener('keydown', this.keydownListener, false);

        delete this.tabTriggers[i].index;
    }

    this.el.classList.remove('is-initialized');
};

new TabWidget(document.getElementsByClassName('js-tabs')[0]);

</script>


<style>

html {
  font-family: Crystal VAR;
}

.tabs__tab-list {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
}

.tabs__trigger {
  background: lightgrey;
  border: 1px solid;
  border-bottom: none;
  color: #000;
  display: block;
  font-weight: bold;
  margin: 0 5px;
  padding: 15px 20px;
  text-decoration: none;
}
.tabs__trigger.is-selected {
  background: lightblue;
}

.tabs__panel {
  border: 1px solid;
  display: none;
  padding: 20px;
}
.is-initialized .tabs__panel {
  display: inherit;
}
.tabs__panel.is-hidden {
  display: none;
}

:focus {
  box-shadow: 0 0 4px dodgerblue;
  outline: none;
}

</style>
