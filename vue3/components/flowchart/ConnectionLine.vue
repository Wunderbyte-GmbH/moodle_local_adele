<script setup>
import { connectionExists, getBezierPath, useVueFlow } from '@vue-flow/core'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  sourceX: {
    type: Number,
    required: true,
  },
  sourceY: {
    type: Number,
    required: true,
  },
  targetX: {
    type: Number,
    required: true,
  },
  targetY: {
    type: Number,
    required: true,
  },
  targetPosition: {
    type: String,
    require: true, // Corrected typo
  },
  sourcePosition: {
    type: String,
    required: true,
  },
})

const { getNodes, connectionStartHandle, onConnectEnd, addEdges, edges } = useVueFlow()

const closest = reactive({
  node: null,
  handle: null,
  startHandle: null, // Initialize startHandle
})

const canSnap = ref(false)

const HIGHLIGHT_COLOR = '#f59e0b'

const SNAP_HIGHLIGHT_COLOR = '#10b981'

const MIN_DISTANCE = 75

const SNAP_DISTANCE = 30

watch([() => props.targetY, () => props.targetX], (_, __, onCleanup) => {
  // Existing watch logic...
})

const path = computed(() => getBezierPath(props))

onConnectEnd(() => {
  if (closest.startHandle && closest.handle && closest.node) {
    if (canSnap.value) {
      addEdges([
        {
          sourceHandle: closest.startHandle.handleId,
          source: closest.startHandle.nodeId,
          target: closest.node.id,
          targetHandle: closest.handle.id,
        },
      ])
    }
  }
})

const strokeColor = computed(() => {
  if (canSnap.value) {
    return SNAP_HIGHLIGHT_COLOR
  }

  if (closest.node) {
    return HIGHLIGHT_COLOR
  }

  return '#222'
})

// Add the onConnectionStart method
const onConnectionStart = (startHandle) => {
  closest.startHandle = startHandle
}
</script>

<template>
  <g>
    <path :d="path[0]" class="vue-flow__connection-path" />
    <circle :cx="targetX" :cy="targetY" fill="#fff" :stroke="strokeColor" :r="3" :stroke-width="1.5" />
  </g>
</template>