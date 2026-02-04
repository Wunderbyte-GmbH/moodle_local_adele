@local @local_adele @javascript

Feature: As an admin I perform basic adele actions - create, update, duplicate, delete.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | user     | Username  | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname | summary     |
      | Course 1 | C1        | LP Course 1 |
      | Course 2 | C2        | LP Course 2 |
      | Course 3 | C3        | LP Course 3 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user     | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following config values are set as admin:
      | config            | value                                                      | plugin      |
      | restrictionfilter | timed,timed_duration,specific_course,parent_courses,manual | local_adele |
    And I change viewport size to "1366x3000"

  @javascript
  Scenario: Adele usage: admin create a new learning path
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "Test Learning Path"
    And I set the field "goalsubjectplaceholder" to "Test Learning Path Description"
    And I click on "Select learning path image" "button"
    And I click on ".image-selection-container .image-option-img" "css_element"
    ## Create learning part in visual way. 
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='starting_node']"
    And I click on "[data-id='starting_node']" "css_element"
    And I wait "1" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(2)" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='dndnode_1']"
    And I zoom vue flow to "40" percent
    And I connect vue flow node "[data-id='dndnode_1']" to "[data-id='dndnode_2']"
    And I pan vue flow to "[data-id='starting_node']"
    And I click on "[data-id='starting_node']" "css_element"
    And I wait "1" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(3)" to "[data-id='starting_node']"
    And I pan vue flow to "[data-id='dndnode_1']"
    And I zoom vue flow to "40" percent
    And I connect vue flow node "[data-id='dndnode_2']" to "[data-id='dndnode_3']"
    ## Manually add restriction to the parent nodes.
    And I click on "[data-id='dndnode_2'] .icon-link .fa-lock" "css_element"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(4)" to ".vue-flow__pane.vue-flow__container.draggable"
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "[data-id='dndnode_3'] .icon-link .fa-lock" "css_element"
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(4)" to ".vue-flow__pane.vue-flow__container.draggable"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    ## Save entire learning path and validate its root elements - nodes and connections.
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I should see "Test Learning Path" in the ".learningcardcont .learningcard" "css_element"
    And I click on ".learningcardcont .learningcard " "css_element"
    And I should see "Course 1" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 2" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 3" in the ".vue-flow.learning-path-flow" "css_element"
    And "[data-id=\"dndnode_2dndnode_1\"]" "css_element" should exist
    And "[data-id=\"dndnode_3dndnode_2\"]" "css_element" should exist
