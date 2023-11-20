<template>
    <div class="not-found">
        <h2>{{strings.route_not_found_site_name}}</h2>
        <h3>{{strings.route_not_found}}</h3>

        <div class="learninggoals-edit-add">
            <router-link :to="{ name: 'learninggoals-edit-overview' }" tag="button" class="btn btn-primary">{{strings.learninggoal_form_title_add}}</router-link>
        </div>
    </div>
</template>

<script>
    import { mapState } from 'vuex';

    export default {
        name: "not-found",
        computed: mapState(['strings']),
        created: function() {
        }
    }
</script>

<style scoped>

</style>