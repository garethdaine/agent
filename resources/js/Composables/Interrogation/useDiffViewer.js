import { ref } from 'vue';
import axios from 'axios';

export function useDiffViewer() {
    const diffData = ref(null);
    const diffLoading = ref(false);
    const diffError = ref('');
    const expandedFiles = ref({});

    const fetchDiff = async (sessionId, taskId) => {
        diffLoading.value = true;
        diffError.value = '';
        diffData.value = null;

        try {
            const { data } = await axios.get(
                `/agent/api/v1/interrogation/sessions/${sessionId}/build-tasks/${taskId}/diff`
            );

            diffData.value = data?.data ?? { available: false, files: [] };
        } catch (e) {
            diffError.value = e?.response?.data?.error?.message ?? 'Failed to load diff.';
            diffData.value = { available: false, files: [] };
        } finally {
            diffLoading.value = false;
        }
    };

    const toggleFile = (path) => {
        expandedFiles.value[path] = !expandedFiles.value[path];
    };

    const isFileExpanded = (path) => {
        return !!expandedFiles.value[path];
    };

    const expandAllFiles = () => {
        if (diffData.value?.files) {
            for (const file of diffData.value.files) {
                expandedFiles.value[file.path] = true;
            }
        }
    };

    const collapseAllFiles = () => {
        expandedFiles.value = {};
    };

    const languageFromPath = (path) => {
        const ext = (path.split('.').pop() || '').toLowerCase();
        const map = {
            js: 'javascript',
            jsx: 'javascript',
            ts: 'typescript',
            tsx: 'typescript',
            vue: 'xml',
            php: 'php',
            py: 'python',
            rb: 'ruby',
            go: 'go',
            rs: 'rust',
            java: 'java',
            kt: 'kotlin',
            swift: 'swift',
            css: 'css',
            scss: 'scss',
            less: 'less',
            html: 'xml',
            xml: 'xml',
            json: 'json',
            yaml: 'yaml',
            yml: 'yaml',
            md: 'markdown',
            sql: 'sql',
            sh: 'bash',
            bash: 'bash',
            zsh: 'bash',
            dockerfile: 'dockerfile',
            toml: 'ini',
            ini: 'ini',
            env: 'bash',
        };
        return map[ext] || 'plaintext';
    };

    return {
        diffData,
        diffLoading,
        diffError,
        expandedFiles,
        fetchDiff,
        toggleFile,
        isFileExpanded,
        expandAllFiles,
        collapseAllFiles,
        languageFromPath,
    };
}
