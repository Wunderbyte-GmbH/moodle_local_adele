@local @local_adele @javascript

Feature: As an admin I perform basic adele actions - create, update, duplicate, delete.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname     | email                       |
      | user     | Username  | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher    | Test        | toolgenerator3@example.com  |
      | manager  | Manager    | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user     | C1     | student        |
      | teacher  | C1     | editingteacher |

  @javascript
  Scenario: Adele usage: admin clicks on learningpath button and sees learning paths
    Given I log in as "admin"
    And I wait until the page is ready
    And I click on "Learning Paths" "button"
    And I wait until the page is ready
    Then I should see "Overview of all learning paths"
    And I click on "Add a new learning path" "button"
    And I set the field "goalnameplaceholder" to "New Learning path"
    And I set the field "goalsubjectplaceholder" to "New Learning path description for auto testing"
    And I click on "//*[@id='save-learning-path']" "xpath_element"
