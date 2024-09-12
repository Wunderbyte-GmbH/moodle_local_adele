<script setup>

import { computed, ref } from 'vue'
import { TransitionPresets, useDebounceFn, useTransition, watchDebounced } from '@vueuse/core'
import { getBezierPath, useVueFlow } from '@vue-flow/core'

const props = defineProps({
  id: {
    type: String,
    required: true,
  },
  source: {
    type: String,
    required: true,
  },
  target: {
    type: String,
    required: true,
  },
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
  sourcePosition: {
    type: String,
    required: true,
  },
  targetPosition: {
    type: String,
    required: true,
  },
  data: {
    type: Object,
    required: true,
  },
  markerEnd: {
    type: String,
    required: true,
  },
  hidden: {
    type: Boolean,
    required: false,
    default: false,
  },
})

const curve = ref()

const dot = ref()

const transform = ref({ x: 0, y: 0 })

const showDot = ref(false)

const { fitBounds, onEdgeClick } = useVueFlow()

const last_target_node = ref('')
const last_source_node = ref('')

const width = 300
const height = 600

const emit = defineEmits([
  'end-transition',
]);

const path = computed(() =>
  getBezierPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    sourcePosition: props.sourcePosition,
    targetX: props.targetX,
    targetY: props.targetY,
    targetPosition: props.targetPosition,
  }),
)

const debouncedFitBounds = useDebounceFn(fitBounds, 1, { maxWait: 1 })

onEdgeClick(({ edge }) => {
  if (!showDot.value && edge.id == props.id) {
    const targetisTarget = last_target_node.value != props.target
    const sourceisSource = last_source_node.value != props.source
    last_target_node.value = targetisTarget ? props.target : props.source
    last_source_node.value = sourceisSource ? props.source : props.target

    emit('end-transition', last_source_node.value);
    showDot.value = true
    let totalLength = curve.value.getTotalLength()
    const initialPos = ref(targetisTarget ?? sourceisSource ? 0 : totalLength)
    let stopHandle
    const duration_calaculated = totalLength  * 5

    const duration_maxed = duration_calaculated > 5000 ? 5000 : duration_calaculated
    const output = useTransition(initialPos, {
      duration: duration_maxed,
      transition: TransitionPresets.easeOutCubic,
      onFinished: () => {
        stopHandle?.()
        showDot.value = false
        emit('end-transition', last_target_node.value);
      },
    })
    transform.value = curve.value.getPointAtLength(output.value)
    setTimeout(() => {
      initialPos.value = targetisTarget ?? sourceisSource ? totalLength : 0
      stopHandle = watchDebounced(
        output,
        (next) => {
          if (!showDot.value) {
            return
          }
          const nextLength = curve.value.getTotalLength()
          if (totalLength !== nextLength) {
            totalLength = nextLength
            initialPos.value = targetisTarget ?? sourceisSource ? totalLength : 0
          }
          transform.value = curve.value.getPointAtLength(next)
          const y_offset = targetisTarget ? 450 : 150
          debouncedFitBounds({
            width: width,
            height: height,
            x: transform.value.x - 150,
            y: transform.value.y - y_offset,
          })
        },
        { debounce: 1 },
      )
    }, 500)
  }

})

</script>

<script>
export default {
  inheritAttrs: false,
}
</script>

<template>
  <path
    :id="id"
    ref="curve"
    class="vue-flow__edge-path trans-edge"
    :d="path[0]"
    :marker-end="markerEnd"
  />
  <Transition name="fade">
    <circle
      v-if="showDot"
      ref="dot"
      r="5"
      cy="0"
      cx="0"
      :transform="`translate(${transform.x}, ${transform.y})`"
      style="fill: #fdd023"
    />
  </Transition>
</template>

<style scoped>
.trans-edge{
  stroke: #b1b1b7 !important;
  stroke-width: 5;
}

.fade-enter-active,
.fade-leave-active
{
  transition:opacity .3s ease
  }
.fade-enter-from,
.fade-leave-to{
  opacity:0
  }
</style>