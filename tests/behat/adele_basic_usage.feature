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
    And I follow "Add a new learning path"
    And I set the field "goalnameplaceholder" to "New Learning path"
    And I set the field "goalsubjectplaceholder" to "New Learning path description for auto testing"
    And I click on "//*[@id='save-learning-path']" "xpath_element"
    Then I should see "New Learning path"
    And I wait until the page is ready
    And I follow "Edit"
    And I set the field "goalnameplaceholder" to "Newer Learning path"
    And I set the field "goalsubjectplaceholder" to "Newer Learning path description for auto testing"
    And I click on "//*[@id='cancel-learning-path']" "xpath_element"
    And I follow "Delete"
    Then I should see "Confirm delete"
    And I click on "Confirm delete" "button"
    And I should not see "New Learning path"
    Then I should see "No learning paths to show yet."
    And I follow "Add a new learning path"
    And I set the field "goalnameplaceholder" to "Newest Learning path"
    And I set the field "goalsubjectplaceholder" to "Newest Learning path description for auto testing"
    Then I click on "//*[@id='save-learning-path']" "xpath_element"
    And I follow "Edit"
    And I set the field "goalnameplaceholder" to "Newer Learning path"
    And I set the field "goalsubjectplaceholder" to "Newer Learning path description for auto testing"
    And I click on "//*[@id='save-learning-path']" "xpath_element"
    And I should see "Newer Learning path"
    Then I follow "Duplicate"
    And I click on "//*[@id='local-adele-app']/div/div/div[2]/div/span/div[1]/div/div[1]/div/div/a[3]" "xpath_element"
    Then I should see "Confirm delete"
    And I click on "Confirm delete" "button"
    Then I wait "5" seconds
