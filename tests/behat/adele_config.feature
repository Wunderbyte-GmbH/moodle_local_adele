@local @local_adele @javascript
Feature: As an admin I perform several drag and drop actions.

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
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Tags | testing |
    And I press "Save and display"
    And I wait "1" seconds
  @javascript
  Scenario: Adele usage: admin defines config
    Given I log in as "admin"
    And I wait until the page is ready
    And I follow "Site administration"
    And I wait until the page is ready
    And I follow "Plugins"
    And I follow "Learning path"
    And I select "All courses meeting the other criteria." from the "Activate filter" singleselect
    And I press "Save changes"
    Then I should see "Changes saved"
    And I set the field with xpath "//*[@id='id_s_local_adele_includetags']" to "test"
    And I press "Save changes"
    And I set the field with xpath "//*[@id='id_s_local_adele_includetags']" to "testing"
    And I press "Save changes"
    Then I should see "Changes saved"
