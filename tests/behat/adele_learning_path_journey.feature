@local @local_catquiz @javascript
Feature: As an admin I perform import of catquiz alonf with questions to check Scales and Feedbacks.

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
  Scenario: Adele import: admin uses learning path journey
    Given I log in as "admin"
    And I wait until the page is ready
    Given the following "local_adele > learningpaths" exist:
      | user     | filepath                                           | filename              |
      | admin    | local/adele/tests/fixtures/single_node_journey.json | single_node_journey.json |
    And I click on "Learning Paths" "button"
    And I wait until the page is ready
    Then I should see "Some description for the learning path"
    And I follow "Edit"
    Then I should see "Edit a learning path"
    And I click on "Edit completion criteria" "button"
    Then I should see "Edit Completion criteria of course node"
    And I click on "Go Back to Learningpath" "button"
    Then I should see "Edit a learning path"
    Then I wait "5" seconds
