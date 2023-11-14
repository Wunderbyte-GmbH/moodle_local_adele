
<style scoped>
    .learninggoals-edit-list {
        padding-top: 1rem;
    }
    .learninggoals-edit-add {
        padding-top: 20px;
    }
    .learninggoals-edit-add-form > div > p > input {
        margin-bottom: 5px;
        font-size: 1rem;
    }
    input.thinking_skill[type="text"] {
        border: 1.5px solid #009;
        border-bottom: 2.5px solid #009;
    }
    input.thinking_skill[type="text"]:focus {
        outline: none;
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
        --webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
        --moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
        border-color: #009;
        transition: border linear .2s, box-shadow linear .2s;
    }
    input.content[type="text"] {
        border: 1.5px solid #600;
        border-bottom: 2.5px solid #600;
    }
    input.content[type="text"]:focus {
        outline: none;
        border: 1.5px solid #600;
        border-bottom: 2.5px solid #600;
    }
    input.resource[type="text"] {
        border: 1.5px solid #090;
        border-bottom: 2.5px solid #090;
    }
    input.resource[type="text"]:focus {
        outline: none;
        border: 1.5px solid #090;
        border-bottom: 2.5px solid #090;
    }
    input.product[type="text"] {
        border: 1.5px solid #909;
        border-bottom: 2.5px solid #909;
    }
    input.product[type="text"]:focus {
        outline: none;
        border: 1.5px solid #909;
        border-bottom: 2.5px solid #909;
    }
    input.group[type="text"] {
        border: 1.5px solid #990;
        border-bottom: 2.5px solid #990;
    }
    input.group[type="text"]:focus {
        outline: none;
        border: 1.5px solid #990;
        border-bottom: 2.5px solid #990;
    }
    input[type="text"] {
        transition: border-color 250ms ease;
        appearance: none;
        border-radius: 4px;
        border: 1.5px solid #e9ebeb;
        border-bottom: 2.5px solid #e9ebeb;
        padding: 0.15em 0.3em;
    }
    input[type="text"]:focus {
        outline: none;
        border-color: #999;
    }
    input[type="text"]::-webkit-input-placeholder {
        /* Chrome/Opera/Safari */
        color: rgba(19, 40, 48, 0.54);
    }
    .fa-clipboard {
        cursor: pointer;
        margin-right: 0px;
    }
</style>

<template>
    <div class="learninggoals-edit">
        <div v-if="editingadding == false">
            <h3>{{strings.pluginname}}</h3>
            <div class="learninggoals-edit-add">
                <router-link :to="{ name: 'learninggoal-new' }" tag="button" class="btn btn-primary">{{strings.learninggoal_form_title_add}}</router-link>
            </div>
            <h2>{{strings.overviewlearningpaths}}</h2>
            <div class="description">{{strings.learninggoals_edit_site_description}}</div>
                <span v-if="learningpaths != null && learningpaths[0].name !== 'not found' && learningpaths[0].description !== ''">
                    <ul class="learninggoals-edit-list">
                        <li v-for="singlelearninggoal in learningpaths" style="margin-bottom: 10px">
                            <div class="learninggoal-top-level" v-if="singlelearninggoal.name !== 'not found'">
                                <div>
                                    <b>
                                        {{ singlelearninggoal.description }}
                                    </b>
                                    <router-link :to="{ name: 'learninggoal-edit', params: { learninggoalId: singlelearninggoal.id }}" :title="strings.edit">
                                        <i class="icon fa fa-pencil fa-fw iconsmall m-r-0" :title="strings.edit"></i>
                                    </router-link>
                                    <a href="" v-on:click.prevent="duplicateLearningpath(singlelearninggoal.id)" :title="strings.duplicate">
                                        <i class="icon fa fa-copy fa-fw iconsmall m-r-0" :title="strings.duplicate"></i>
                                    </a>
                                    <a href="" v-on:click.prevent="showDeleteConfirm(singlelearninggoal.id)" :title="strings.delete">
                                        <i class="icon fa fa-trash fa-fw iconsmall" :title="strings.delete"></i>
                                    </a>
                                </div>
                                <div class="alert-danger p-3 m-t-1 m-b-1" v-show="clicked[singlelearninggoal.id]">
                                    <div>{{strings.deletepromptpre}}{{singlelearninggoal.name}}{{strings.deletepromptpost}}</div>
                                    <div class="m-t-1">
                                        <button class="btn btn-danger m-r-0" @click="deleteLearningpathConfirm(singlelearninggoal.id)" :title="strings.btnconfirmdelete">
                                        {{ strings.btnconfirmdelete }}</button>
                                        <button type=button @click="cancelDeleteConfirm(singlelearninggoal.id)" class="btn btn-secondary">{{strings.cancel}}</button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </span>
        </div>
        <div v-if="editingadding == true">
            <h3>{{strings.learninggoal_form_title_edit}}</h3>
            <div class="learninggoals-edit-add-form">
                <div class="mt-3">
                    <button type=button @click.prevent="onSavePath" class="btn btn-primary" :title="strings.save">Save Learning path</button>
                    <button type=button @click.prevent="onCancel" class="btn btn-secondary" :title="strings.cancel">{{strings.cancel}}</button>
                </div>
                <div v-for="goal in learninggoal">
                        <p>
                            <h4>{{ strings.fromlearningtitel }}</h4>
                            <input v-if="$store.state.learningGoalID == 0"
                                v-bind:placeholder="strings.goalnameplaceholder"
                                autofocus
                                type="text"
                                v-autowidth="{maxWidth: '960px', minWidth: '20px', comfortZone: 0}"
                                v-model="goalname">
                            <input v-else
                                type="text"
                                v-autowidth="{maxWidth: '960px', minWidth: '20px', comfortZone: 0}"
                                v-model="goal.name">
                        </p>
                        <p>
                            <h4>{{ strings.fromlearningdescription }}</h4>
                            <input v-if="$store.state.learningGoalID == 0"
                                v-bind:placeholder="strings.goalsubjectplaceholder"
                                autofocus
                                type="textarea"
                                v-autowidth="{maxWidth: '960px', minWidth: '40%', comfortZone: 0}"
                                v-model="goaldescription">
                            <input v-else
                                type="textarea"
                                v-autowidth="{maxWidth: '960px', minWidth: '40%', comfortZone: 0}"
                                v-model="goal.description">
                        </p>
                        <p>
                            <h4>{{ strings.fromavailablecourses }}</h4>
                            <div v-for="availablecourse in availablecourses">
                                <li>
                                    {{ availablecourse.fullname }}
                                </li>
                            </div>
                            
                        </p>
                </div>
                
            </div>
        </div>
    </div>
</template>

<script>
    import { mapState } from 'vuex';
    export default {
        name: "learninggoals-edit",
        data: function() {
            return {
                goalname: '',
                goaldescription: '',
                editingadding: false,
                selectedTabId: 0,
                clicked: {},
            };
        },
        computed: mapState(['strings', 'learninggoal', 'learningGoalID', 'availablecourses', 'learningpaths', 'learningpath']),
        watch: {
            goalname: function () {
                this.learninggoal[0].name = this.goalname
            },
            goaldescription: function () {
                this.learninggoal[0].description = this.goaldescription
            }
        },
        methods: {
            async showForm(learninggoalId = null, selectedTabId = 0) {
                this.goalname = '';
                this.goalsubject = '';
                let args = {};
                if (learninggoalId) {
                    this.$store.state.learningGoalID = learninggoalId;
                    this.$store.dispatch('fetchLearningpath');
                    this.editingadding = true;
                    // Do something here in case of an edit.
                } else {
                    this.$store.dispatch('fetchLearningpath');
                    this.editingadding = true;
                    // Do something here in case of an add.
                }
                if (selectedTabId) {
                    this.selectedTabId = selectedTabId;
                }
                window.scrollTo(0,0);
                // This has to happen after the save button is hit.
            },
            checkRoute(route) {
                if (route.name === 'learninggoal-edit') {
                    this.$nextTick(this.showForm.bind(this, route.params.learninggoalId, 0));
                } else if (route.name === 'learninggoal-new') {
                    this.$nextTick(this.showForm.bind(this, null, 0));
                }
            },
            onCancel(){
                this.$store.state.learningGoalID = 0;
                this.editingadding = false;
                this.selectedTabId = 0;
                this.$router.push({name: 'learninggoals-edit-overview'});
            },
            onSavePath() {
                let result = {
                    learninggoalid: this.$store.state.learningGoalID,
                    name: this.learninggoal[0].name,
                    description: this.learninggoal[0].description,
                };
                this.$store.dispatch('saveLearningpath', result);
                this.$store.dispatch('fetchLearningpaths');
                this.$store.state.learningGoalID = 0;
                this.editingadding = false;
                this.$router.push({name: 'learninggoals-edit-overview'});
                window.scrollTo(0,0);
            },
            showDeleteConfirm(index){
                // Dismiss other open confirm delete prompts.
                this.clicked = {};
                // Show the confirm delete prompt.
                this.$set(this.clicked, index, true)
            },
            cancelDeleteConfirm(index){
                if (this.clicked.hasOwnProperty(index))
                    this.$set(this.clicked, index, !this.clicked[index])
            },
            deleteLearningpathConfirm(learninggoalid) {
                let result = {
                    learninggoalid: learninggoalid,
                };
                this.$store.dispatch('deleteLearningpath', result);
                this.clicked = {};
            },
            duplicateLearningpath(learninggoalid) {
                let result = {
                    learninggoalid: learninggoalid,
                };
                this.$store.dispatch('duplicateLearningpath', result);
            }
        },
        created: function() {
            this.$store.dispatch('fetchLearningpaths');
            this.$store.dispatch('fetchAvailablecourses');
            this.checkRoute(this.$route);
        },
        beforeRouteUpdate(to, from, next) {
            this.checkRoute(to);
            next();
        },
    }
</script>