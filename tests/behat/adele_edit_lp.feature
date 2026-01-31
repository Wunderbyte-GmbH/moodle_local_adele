@local @local_adele @javascript

Feature: As an admin I perform editing of the adele learning plan.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | student  | Student   | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname | summary |
      | Course 1 | C1        | LP Course 1 |
      | Course 2 | C2        | LP Course 2 |
      | Course 3 | C3        | LP Course 3 |
      | Course 4 | C4        | LP Course 4 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student  | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "local_adele > learningpaths" exist:
      | name     | description   | filepath                                               | courses     | image                                                 |
      | Test LP1 | Test LP1 Desc | local/adele/tests/fixtures/learning_plan3_courses.json | C1,C2,C3,C4 | /local/adele/public/node_background_image/image_1.jpg |
    And I change viewport size to "1366x3000"

  @javascript
  Scenario: Adele usage: admin edit a learning path created via DB
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I should see "Learning Path 2025" in the ".learningcardcont .learningcard" "css_element"
    And I should see "Learning Path 2025 Description" in the ".learningcardcont .learningcard" "css_element"
    And I click on ".learningcardcont .learningcard " "css_element"
    And I should see "Course 1" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 2" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 3" in the ".vue-flow.learning-path-flow" "css_element"
    And "[data-id=\"dndnode_2dndnode_1\"]" "css_element" should exist
    And "[data-id=\"dndnode_3dndnode_2\"]" "css_element" should exist
