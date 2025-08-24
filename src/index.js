import React, {useState, useEffect} from 'react';
import axios from 'axios';
import {
    LinearProgress,
    Container,
    TextField,
    Button,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    Typography, AppBar, Toolbar, Tab, Tabs, Box
} from '@mui/material';
import domReady from '@wordpress/dom-ready';
import {createRoot} from '@wordpress/element';
import {ToastContainer, toast} from 'react-toastify'; // 导入 react-toastify
import {HashRouter as Router, Route, Routes, Link} from 'react-router-dom';
import './i18n';
import { useTranslation } from 'react-i18next';

import './style/main.scss';
import 'react-toastify/dist/ReactToastify.css'; // 导入样式

import AttachmentStatistics from "./AttachmentStatistics";
import Setting from "./setting";
import Converter from "./AttachmentStorageConverter";

function NotFound() {
    const { t } = useTranslation();
    return <h2>{t('pageNotFound')}</h2>;
}

const App = () => {
    const { t } = useTranslation();
    const [value, setValue] = useState(0); // 当前选中的 Tab 索引

    // 处理选中的 Tab
    const handleChange = (event, newValue) => {
        setValue(newValue);
    };

    return (
        <Router>
            <Box sx={{display: 'flex', flexDirection: 'column'}}>
                {/* 顶部应用栏 */}
                <AppBar position="sticky">
                    <Toolbar>
                        <Typography variant="h6" sx={{flexGrow: 1}}>
                            {t('s3keep')}
                        </Typography>
                    </Toolbar>
                </AppBar>

                {/* Tabs 导航 */}
                <Box sx={{width: '100%', bgcolor: 'background.paper'}}>
                    <Tabs value={value} onChange={handleChange} centered>
                        <Tab label={t('statistics')} component={Link} to=""/>
                        <Tab label={t('s3Setting')} component={Link} to="/setting"/>
                        <Tab label={t('s3Convert')} component={Link} to="/convert"/>
                    </Tabs>
                </Box>

                {/* 页面内容 */}
                <Box
                    component="main"
                    sx={{
                        flexGrow: 1,
                        bgcolor: 'background.default',
                        padding: 3,
                    }}
                >
                    {/* 页面路由 */}
                    <Routes>
                        <Route path="/setting" element={<Setting/>}/>
                        <Route path="/convert" element={<Converter/>}/>
                        <Route path="" element={<AttachmentStatistics/>}/>

                        <Route path="*" element={<NotFound/>}/>
                    </Routes>
                </Box>
            </Box>
        </Router>
    );
};

domReady(() => {
    const root = createRoot(document.getElementById('s3keeper_root'));
    root.render(<App/>);
});
export default App;