<template>
  <div>
    <div
      v-if="date.start"
      class="row mb-2"
    >
      <div class="col-4 text-left">
        <b>
          <i class="fas fa-calendar mr-2" />
          {{ store.state.strings.nodes_items_start }}
        </b>
      </div>
      <div class="col-8 text-right">
        {{ formatDate(props.date.start) }}
      </div>
    </div>
    <div
      v-if="date.end"
      class="row mb-2"
    >
      <div class="col-4 text-left">
        <b>
          <i class="fas fa-calendar mr-2" />
          {{ store.state.strings.nodes_items_end }}
        </b>
      </div>
      <div class="col-8 text-right">
        {{ formatDate(props.date.end) }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { useStore } from 'vuex';
  const store = useStore();

  interface StartEndDate {
    start: string;
    end: string;
  }

  const props = defineProps<{
    date: StartEndDate;
  }>();

  const formatDate = (dateString: string): string  => {
    const options: Intl.DateTimeFormatOptions =
      {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: false
      };
    const formattedDate = new Date(dateString).toLocaleString('en-US', options);
    return formattedDate;
  }
</script>
