import { HashRouter, Navigate, Route, Routes } from 'react-router-dom';
import Dashboard from './pages/Dashboard.jsx';
import ProjectView from './pages/ProjectView.jsx';

export default function App() {
  return (
    <HashRouter>
      <div style={{ minHeight: '100vh' }}>
        <Routes>
          <Route path="/" element={<Dashboard />} />
          <Route path="/project/:projectId" element={<ProjectView />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </HashRouter>
  );
}
