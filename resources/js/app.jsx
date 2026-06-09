import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import Vera from './Vera.jsx';

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <Vera />
    </StrictMode>
);
