import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './styles/app.css';
import './styles/admin-layout.css';

const el = document.getElementById('root');
if (el) {
  createRoot(el).render(<App />);
}
