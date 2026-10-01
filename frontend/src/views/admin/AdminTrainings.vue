<script setup>
import { onMounted, ref } from 'vue'
import { getAdminTrainings, createTraining, assignTraining } from '@/services/portalService'
import { useToastStore } from '@/stores/toast'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const toast = useToastStore()
const items = ref([])
const loading = ref(true)
const showAdd = ref(false)
const submitting = ref(false)

const form = ref({ title: '', description: '', passScore: 80 })
const sections = ref([{ title: '1. Introduction', body: '' }])
const quiz = ref([{ question: '', options: ['', ''], answer: 0 }])

async function load() {
  loading.value = true
  try {
    items.value = (await getAdminTrainings()).data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function save() {
  if (!form.value.title.trim()) return toast.show('Title is required.')
  if (!quiz.value[0].question.trim()) return toast.show('At least one quiz question is required.')

  submitting.value = true
  try {
    await createTraining({
      ...form.value,
      sections: sections.value,
      quiz: quiz.value.map((q) => ({ ...q, options: q.options.filter((o) => o.trim()) })),
    })
    toast.show('✓ Training created. Use "Assign" to publish it to staff.')
    showAdd.value = false
    form.value = { title: '', description: '', passScore: 80 }
    sections.value = [{ title: '1. Introduction', body: '' }]
    quiz.value = [{ question: '', options: ['', ''], answer: 0 }]
    await load()
  } catch (e) {
    toast.show(Object.values(e.errors ?? {})[0]?.[0] ?? e.message)
  } finally {
    submitting.value = false
  }
}

async function assign(t, all) {
  const dueDate = prompt('Due date (YYYY-MM-DD, optional):') || undefined
  const res = await assignTraining({
    trainingId: t.id,
    dueDate,
    ...(all ? {} : {}),
  })
  toast.show('✓ ' + res.message)
  await load()
}
</script>

<template>
  <div>
    <div class="page-head">
      <div>
        <h1>Training Manager</h1>
        <div class="sub">Build courses with sections + quiz, then assign to all staff.</div>
      </div>
      <button class="btn primary" @click="showAdd = true">+ Create Training</button>
    </div>

    <section class="card">
      <LoadingState v-if="loading" />
      <table v-else class="table">
        <thead><tr><th>Training</th><th class="num">Assigned</th><th class="num">Completions</th><th class="num">Pass Mark</th><th></th></tr></thead>
        <tbody>
          <tr v-for="t in items" :key="t.id">
            <td><strong>{{ t.title }}</strong><div class="help" v-if="t.description">{{ t.description.slice(0, 70) }}</div></td>
            <td class="num">{{ t.assignments }}</td>
            <td class="num">{{ t.completions }}</td>
            <td class="num">{{ t.passScore }}%</td>
            <td><button class="btn sm primary" @click="assign(t, true)">Assign to All Staff</button></td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="modal-overlay" :class="{ show: showAdd }">
      <div class="modal" v-if="showAdd" style="max-width:640px; max-height:85vh; overflow-y:auto">
        <h3>New Training Course</h3>

        <div class="field"><label>Title <span class="req">*</span></label><input v-model="form.title" placeholder="e.g. Environmental Safeguarding"></div>
        <div class="field"><label>Description</label><input v-model="form.description"></div>

        <div class="card-head"><h2 style="font-size:13px">Sections</h2></div>
        <div v-for="(s, i) in sections" :key="i" class="item-row">
          <div class="grow">
            <div class="field"><label>Section Title</label><input v-model="s.title"></div>
            <div class="field"><label>Content</label><textarea v-model="s.body" rows="2"></textarea></div>
          </div>
          <button class="remove-btn" @click="sections.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" style="margin-bottom:16px" @click="sections.push({ title: '', body: '' })">+ Add Section</button>

        <div class="card-head"><h2 style="font-size:13px">Quiz Questions</h2></div>
        <div v-for="(q, i) in quiz" :key="'q' + i" class="item-row">
          <div class="grow">
            <div class="field"><label>Question {{ i + 1 }}</label><input v-model="q.question"></div>
            <div class="row2">
              <div class="field"><label>Option A</label><input v-model="q.options[0]"></div>
              <div class="field"><label>Option B</label><input v-model="q.options[1]"></div>
            </div>
            <div class="row2">
              <div class="field"><label>Option C (optional)</label><input v-model="q.options[2]"></div>
              <div class="field"><label>Option D (optional)</label><input v-model="q.options[3]"></div>
            </div>
            <div class="field">
              <label>Correct Answer</label>
              <select v-model.number="q.answer">
                <option :value="0">A</option><option :value="1">B</option>
                <option :value="2">C</option><option :value="3">D</option>
              </select>
            </div>
          </div>
          <button class="remove-btn" @click="quiz.splice(i, 1)">✕</button>
        </div>
        <button class="add-btn" style="margin-bottom:16px" @click="quiz.push({ question: '', options: ['', ''], answer: 0 })">+ Add Question</button>

        <div class="field" style="max-width:140px"><label>Pass Score %</label><input v-model.number="form.passScore" type="number" min="50" max="100"></div>

        <div class="row">
          <button class="btn secondary" @click="showAdd = false">Cancel</button>
          <button class="btn primary" :disabled="submitting" @click="save">Create Training</button>
        </div>
      </div>
    </div>
  </div>
</template>
