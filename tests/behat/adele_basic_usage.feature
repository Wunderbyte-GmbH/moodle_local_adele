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

  @javascript
  Scenario: Adele usage: admin create a new learning path
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "Test Learning Path"
    And I set the field "goalsubjectplaceholder" to "Test Learning Path Description"
    And I click on "Select learning path image" "button"
    And I click on ".image-selection-container .image-option-img" "css_element"
    ##And I wait "30" seconds
