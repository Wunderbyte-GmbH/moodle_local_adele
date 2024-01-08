<script setup>
    // Import needed libraries
    import { defineProps } from 'vue';

    const props = defineProps({
        childNodes: Array,
    });
</script>

<template>
    <div class="card-body">
    <h5 class="card-title">
        <i class="fa fa-arrow-circle-down"></i> Child Nodes:
    </h5>
    <ul class="list-group list-group-flush">
        <template v-if="props.childNodes.length > 0">
        <div v-for="child in props.childNodes">
            <li class="list-group-item">
            {{ child.data.fullname }}
            </li>
        </div>
        </template>
        <template v-else>
        <li class="list-group-item">
            No child nodes found.
        </li>
        </template>
    </ul>
    </div>
</template>
