<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getTraining, saveTrainingProgress, submitQuiz } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/utils/format'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const data = ref(null)
const loading = ref(true)
const section = ref(0)
const answers = ref({})
const result = ref(null)
const submitting = ref(false)

onMounted(async () => {
  try {
    data.value = await getTraining(route.params.id)
    const saved = data.value.assignment.progress ?? 0
    // Jump to quiz when the reader has finished the content sections
    if (saved >= 100) section.value = Math.max(0, data.value.training.sections.length - 1)
  } finally {
    loading.value = false
  }
})

const a = computed(() => data.value?.assignment ?? {})
const t = computed(() => data.value?.training ?? {})
const totalSections = computed(() => t.value.sections?.length ?? 0)

const pct = computed(() => {
  if (a.value.status === 'Complete') return 100
  if (section.value >= totalSections.value - 1) return 100
  return Math.round(((section.value + 1) / totalSections.value) * 100)
})

async function nextSection() {
  if (section.value < totalSections.value - 1) {
    section.value++
  }
  try {
    await saveTrainingProgress(route.params.id, pct.value)
  } catch { /* silent */ }
}

async function submit() {
  const unanswered = (t.value.quiz ?? []).findIndex((_, i) => answers.value[i] === undefined)
  if (unanswered !== -1) {
    toast.show(`Please answer question ${unanswered + 1}.`)
    return
  }
  submitting.value = true
  try {
    result.value = await submitQuiz(route.params.id, Object.values(answers.value).map(Number))
    if (result.value.passed) {
      toast.show('✓ ' + result.value.message)
    } else {
      toast.show(result.value.message)
    }
  } catch (e) {
    toast.show(e.message)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>{{ t.title ?? 'Training' }}</h1>
        <div class="sub">{{ t.description }}</div>
      </div>
      <RouterLink class="btn secondary" to="/training">← Back to list</RouterLink>
    </div>

    <LoadingState v-if="loading" />

    <template v-else>
      <div class="card" v-if="a.status === 'Complete'">
        <div class="card-head"><h2>✓ Training Completed</h2></div>
        <p style="font-size:13.5px">
          Score: <strong>{{ a.score }}%</strong> · Completed: <strong>{{ formatDate(a.completedAt) }}</strong>
        </p>
        <div class="actions" style="justify-content:flex-start">
          <RouterLink class="btn primary" :to="`/training/${route.params.id}/certificate`">🎓 Download Certificate</RouterLink>
        </div>
      </div>

      <template v-else>
        <div class="card">
          <div class="card-head"><h2>Progress</h2><div class="spacer"></div><span class="badge info">{{ pct }}%</span></div>
          <div class="progress"><div :style="{ width: pct + '%' }"></div></div>
        </div>

        <!-- Content sections -->
        <div class="card" v-if="section < totalSections - 1">
          <div class="card-head"><h2>{{ t.sections[section].title }}</h2></div>
          <p style="font-size:14px; line-height:1.8; color:var(--text)">{{ t.sections[section].body }}</p>
          <div class="actions">
            <button v-if="section > 0" class="btn secondary" @click="section--">← Previous</button>
            <button class="btn primary" @click="nextSection">Continue →</button>
          </div>
        </div>

        <!-- Quiz (last section) -->
        <div class="card" v-else>
          <div class="card-head"><h2>{{ t.sections[section]?.title ?? 'Quiz' }}</h2>
            <div class="spacer"></div>
            <span class="badge neutral">Pass mark: {{ t.passScore }}%</span>
          </div>

          <div v-for="(q, i) in t.quiz" :key="i" class="quiz-q">
            <div class="q">{{ i + 1 }}. {{ q.question }}</div>
            <label v-for="(opt, oi) in q.options" :key="oi" class="quiz-opt" :class="{ sel: answers[i] === oi }">
              <input type="radio" :name="'q' + i" :value="oi" v-model="answers[i]">
              {{ opt }}
            </label>
          </div>

          <div v-if="result" class="card" style="background:var(--field-bg); margin:14px 0">
            <strong>{{ result.passed ? '🎉' : '😕' }} Score: {{ result.score }}%</strong>
            <div class="help">{{ result.message }} (pass mark {{ result.passScore }}%)</div>
          </div>

          <div class="actions">
            <button class="btn secondary" @click="section--">← Previous</button>
            <button class="btn primary" :disabled="submitting" @click="submit">✓ Submit Answers</button>
            <RouterLink v-if="result?.passed" class="btn success" :to="`/training/${route.params.id}/certificate`">🎓 Certificate</RouterLink>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
