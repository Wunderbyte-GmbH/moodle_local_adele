@local @local_adele @javascript

Feature: As an admin I perform basic adele actions - create, update, duplicate, delete.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | user     | Username  | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
      | Course 2 | C2        |
      | Course 3 | C3        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user     | C1     | student        |
      | teacher  | C1     | editingteacher |
    And I change viewport size to "1366x2000"

  @javascript
  Scenario: Adele usage: admin create a new learning path
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "Test Learning Path"
    And I set the field "goalsubjectplaceholder" to "Test Learning Path Description"
    And I click on "Select learning path image" "button"
    And I click on ".image-selection-container .image-option-img" "css_element"
    And I wait "1" seconds
    ##And I zoom vue flow to "40" percent
    And I wait "2" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(2)" to "[data-id='starting_node']"
    And I wait "2" seconds
    ##And I zoom vue flow to "80" percent
    And I wait "2" seconds
    And I pan vue flow to "[data-id='starting_node']"
    ##And I click on "[data-id='starting_node']" "css_element"
    And I wait "2" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to "[data-id='starting_node']"
    ##And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :first-child" to "[data-id='dndnode_1']" as "[data-id='dropzone_parent']"
    And I wait "2" seconds
    ##And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(3)" to "[data-id='dndnode_2']" as "[data-id='dropzone_child']"
    And I wait "30" seconds
